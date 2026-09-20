<?php

namespace App\Support\Import;

use App\Models\Company;
use App\Models\MetricDefinition;
use App\Support\RiskService;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DynamicImportService
{
    public const STRUCTURE = [
        'cliente_id' => 'Código do cliente',
        'mes_ref' => 'Mês de referência',
        'segmento' => 'Segmento',
        'porte' => 'Porte',
        'plano' => 'Plano',
        'valor_mensal' => 'Valor mensal do contrato',
    ];

    /** Modelo em XLSX (Leia-me, dicionário e abas ligadas por cliente_id). Devolve o caminho do arquivo temporário. */
    public static function xlsxModelo(Company $company): string
    {
        return ModeloPlanilha::gerar($company);
    }

    /** Colunas com significado próprio: não viram métrica. Ligam-se ao cliente (situação, cancelamento e início do contrato). */
    /** Campos da estrutura que a planilha pode omitir: ficam como "Não informado". */
    public const ESTRUTURA_OPCIONAL = ['segmento', 'plano'];

    public const NAO_INFORMADO = 'Não informado';

    public const OPCIONAIS = ['inicio_contrato', 'situacao', 'mes_cancelamento'];

    /** @return array<string, string> campo => cabeçalho encontrado na planilha */
    public static function colunasOpcionais(array $headers): array
    {
        $mapa = [];
        foreach ($headers as $header) {
            foreach (self::OPCIONAIS as $campo) {
                if (self::normalizar((string) $header) === self::normalizar($campo)) {
                    $mapa[$campo] ??= $header;
                }
            }
        }

        return $mapa;
    }

    public static function csvModelo(): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, array_keys(self::STRUCTURE), ';', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /** @return array{structure: array<string, string|null>, metrics: list<array<string, mixed>>} */
    public static function sugerir(Company $company, array $headers, array $preview, array $dicionario = []): array
    {
        self::validarCabecalhos($headers);
        $suggestions = ImportService::sugerirMapeamento($headers);
        $structure = array_intersect_key($suggestions, self::STRUCTURE);
        $used = [...array_filter($structure), ...array_values(self::colunasOpcionais($headers))];
        $metrics = [];

        foreach ($headers as $header) {
            if (! in_array($header, $used, true)) {
                $metrics[] = self::sugerirMetrica($company, $header, $preview, $dicionario);
            }
        }

        return ['structure' => $structure, 'metrics' => $metrics];
    }

    /** @param  array<string, array<string, string>>  $dicionario  campo => linha do dicionário da planilha (opcional) */
    public static function sugerirMetrica(Company $company, string $header, array $preview, array $dicionario = []): array
    {
        $name = preg_replace('/^metrica__/', '', $header);
        $normalized = self::normalizar($name);
        $existing = $company->metricDefinitions()->get()->first(fn ($definition) => self::normalizar($definition->code) === $normalized || self::normalizar($definition->label) === $normalized
        );
        $samples = collect($preview)->pluck($header)->filter(fn ($value) => trim((string) $value) !== '');
        $type = $samples->contains(fn ($value) => str_contains((string) $value, 'R$'))
            ? 'currency' : ($samples->contains(fn ($value) => str_contains((string) $value, '%')) || str_contains($header, '%')
                ? 'percentage' : 'decimal');
        if ($samples->contains(fn ($value) => ! preg_match('/^-?(?:R\$\s*)?[\d.,]+\s*%?$/', trim((string) $value)))) {
            $type = $samples->isNotEmpty() && $samples->every(fn ($value) => self::data(trim((string) $value)) !== null) ? 'date' : 'text';
        }
        $dic = collect($dicionario)->first(fn (array $linha, string $campo): bool => self::normalizar($campo) === $normalized) ?? [];
        $type = self::tipoDoDicionario($dic['tipo'] ?? '') ?? $type;
        $code = Str::slug(Str::ascii($name), '_');
        if (! preg_match('/^[a-z]/', $code)) {
            $code = 'm_'.$code;
        }

        return [
            'column' => $header,
            'target' => $existing ? (string) $existing->id : 'new',
            'code' => substr($code, 0, 40),
            'label' => $existing?->label ?? Str::headline($name),
            'description' => $existing?->description ?? ($dic['descricao'] ?? ''),
            'value_type' => $existing?->value_type ?? $type,
            'direction' => $existing?->direction ?? self::direcaoDoDicionario($dic['pioraquando'] ?? ''),
            'healthy_value' => $existing?->healthy_value ?? self::numeroDoDicionario($dic['valorsaudavel'] ?? ''),
            'critical_value' => $existing?->critical_value ?? self::numeroDoDicionario($dic['valorcritico'] ?? ''),
            'weight' => $existing?->weight ?? (MetricDefinition::semScore($type) ? 0 : (self::numeroDoDicionario($dic['peso'] ?? '') ?: 10)),
        ];
    }

    /** @param array{cabecalhos: array, linhas: array} $table */
    public static function importar(Company $company, array $table, array $structure, array $metrics): array
    {
        [$mapping, $definitions, $records] = self::validar($company, $table, $structure, $metrics);

        $stats = DB::transaction(function () use ($company, $mapping, $definitions, $records): array {
            return app(CompanyContext::class)->within($company, function () use ($company, $mapping, $definitions, $records): array {
                $definitionIds = [];
                foreach ($definitions as $column => $definition) {
                    $definitionIds[$column] = isset($definition['id'])
                        ? $definition['id']
                        : $company->metricDefinitions()->create($definition['new'] + ['enabled' => true])->id;
                }

                $customers = [];
                foreach ($records as $record) {
                    $code = $record['code'];
                    if (! isset($customers[$code]) || $record['month'] >= $customers[$code]['latest_month']) {
                        $customers[$code] = [
                            'latest_month' => $record['month'],
                            'segment' => $record['segment'], 'size' => $record['size'], 'plan' => $record['plan'],
                            'monthly_value' => $record['monthly_value'],
                            'status' => $record['status'], 'cancelled' => $record['cancelled_month'], 'inicio' => $record['started_at'],
                            'started' => min($record['month'], $customers[$code]['started'] ?? $record['month']),
                        ];
                    } else {
                        $customers[$code]['started'] = min($record['month'], $customers[$code]['started']);
                    }
                }
                $now = now();
                $temSituacao = collect($records)->contains(fn (array $r): bool => $r['status'] !== null);
                $temInicio = collect($records)->contains(fn (array $r): bool => $r['started_at'] !== null);
                $customerRows = [];
                foreach ($customers as $code => $customer) {
                    $customerRows[] = [
                        'company_id' => $company->id, 'external_code' => $code,
                        'segment' => $customer['segment'], 'size' => $customer['size'], 'plan' => $customer['plan'],
                        'monthly_value' => $customer['monthly_value'], 'contracted_sla_hours' => 0,
                        'contract_started_at' => $customer['inicio'] ?? $customer['started'].'-01', 'status' => $customer['status'] ?? 'Ativo',
                        'cancelled_at' => isset($customer['cancelled']) ? $customer['cancelled'].'-01' : null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($customerRows, 250) as $batch) {
                    DB::table('customers')->upsert($batch, ['company_id', 'external_code'], [
                        'segment', 'size', 'plan', 'monthly_value', 'updated_at',
                        ...($temSituacao ? ['status', 'cancelled_at'] : []), ...($temInicio ? ['contract_started_at'] : []),
                    ]);
                }
                $ids = DB::table('customers')->where('company_id', $company->id)
                    ->whereIn('external_code', array_keys($customers))->pluck('id', 'external_code');
                $periodRows = array_map(fn ($record): array => [
                    'company_id' => $company->id, 'customer_id' => $ids[$record['code']],
                    'reference_month' => $record['month'].'-01', 'monthly_value' => $record['monthly_value'],
                    'created_at' => $now, 'updated_at' => $now,
                ], $records);
                foreach (array_chunk($periodRows, 250) as $batch) {
                    DB::table('customer_periods')->upsert($batch, ['customer_id', 'reference_month'], ['monthly_value', 'updated_at']);
                }
                $valueRows = [];
                $missing = [];
                foreach ($records as $record) {
                    foreach ($record['values'] as $column => $parsed) {
                        $identity = [$ids[$record['code']], $definitionIds[$column], $record['month'].'-01'];
                        if ($parsed === null) {
                            $missing[] = $identity;

                            continue;
                        }
                        $valueRows[] = [
                            'company_id' => $company->id, 'customer_id' => $identity[0],
                            'metric_definition_id' => $identity[1], 'reference_month' => $identity[2],
                            'value' => $parsed['value'], 'text_value' => $parsed['text_value'],
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                    }
                }
                foreach ($missing as [$customerId, $definitionId, $month]) {
                    DB::table('metric_values')->where('company_id', $company->id)->where('customer_id', $customerId)
                        ->where('metric_definition_id', $definitionId)->where('reference_month', $month)->delete();
                }
                foreach (array_chunk($valueRows, 250) as $batch) {
                    DB::table('metric_values')->upsert($batch, ['customer_id', 'metric_definition_id', 'reference_month'], ['value', 'text_value', 'updated_at']);
                }
                $company->update(['imported_at' => $now, 'column_mapping' => $mapping]);

                return ['clientes' => count($customers), 'meses' => count($records), 'valores_metricas' => count($valueRows), 'novas_metricas' => count(array_filter($definitions, fn ($definition) => isset($definition['new'])))];
            });
        });

        Backtest::invalidar($company);
        RiskService::recalcular($company);

        return $stats;
    }

    /** @return array{array<string, string>, array<string, array>, list<array>} */
    private static function validar(Company $company, array $table, array $structure, array $metrics): array
    {
        $headers = $table['cabecalhos'];
        self::validarCabecalhos($headers);
        if (! $table['linhas']) {
            throw new InvalidArgumentException('A planilha não contém linhas de dados.');
        }
        foreach (self::ESTRUTURA_OPCIONAL as $campo) {
            if (($structure[$campo] ?? '') === '') {
                $structure[$campo] = null; // segmento e plano são opcionais
            }
        }
        $usadas = array_filter($structure, fn ($coluna): bool => $coluna !== null && $coluna !== '');
        if (array_keys($structure) !== array_keys(self::STRUCTURE)
            || count(array_diff_key(self::STRUCTURE, $usadas, array_flip(self::ESTRUTURA_OPCIONAL))) > 0
            || count(array_unique($usadas)) !== count($usadas)) {
            throw new InvalidArgumentException('Confirme as colunas obrigatórias (todas, menos segmento e plano) sem repetições.');
        }
        foreach ($usadas as $column) {
            if (! in_array($column, $headers, true)) {
                throw new InvalidArgumentException('Uma coluna estrutural não foi encontrada na planilha.');
            }
        }
        $opcionais = self::colunasOpcionais($headers);
        $metricHeaders = array_values(array_diff($headers, array_values($usadas), array_values($opcionais)));
        $mappedHeaders = array_column($metrics, 'column');
        if (! $metricHeaders || count($metricHeaders) !== count($mappedHeaders)
            || array_diff($metricHeaders, $mappedHeaders) || array_diff($mappedHeaders, $metricHeaders)
            || count(array_unique($mappedHeaders)) !== count($mappedHeaders)) {
            throw new InvalidArgumentException('Cada coluna após o mapeamento estrutural deve corresponder a uma métrica.');
        }

        $known = $company->metricDefinitions()->get()->keyBy('id');
        $definitions = [];
        $usedIds = [];
        $usedCodes = [];
        foreach ($metrics as $metric) {
            $column = $metric['column'];
            $target = (string) ($metric['target'] ?? 'new');
            if ($target !== 'new') {
                $definition = $known->get((int) $target);
                if (! $definition || isset($usedIds[$definition->id])) {
                    throw new InvalidArgumentException("A métrica de {$column} não pertence a esta empresa ou foi mapeada duas vezes.");
                }
                $usedIds[$definition->id] = true;
                $definitions[$column] = ['id' => $definition->id, 'type' => $definition->value_type];

                continue;
            }
            $code = trim((string) ($metric['code'] ?? ''));
            $label = trim((string) ($metric['label'] ?? ''));
            $description = trim((string) ($metric['description'] ?? ''));
            $type = (string) ($metric['value_type'] ?? '');
            $direction = (string) ($metric['direction'] ?? '');
            $weight = $metric['weight'] ?? null;
            if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $code) || isset($usedCodes[$code])
                || $known->contains('code', $code) || $label === '' || mb_strlen($label) > 100
                || $description === '' || mb_strlen($description) > 1000
                || ! array_key_exists($type, MetricDefinition::TYPES)
                || ! is_numeric($weight) || (float) $weight < 0 || (float) $weight > 100) {
                throw new InvalidArgumentException("Revise código, nome, descrição, tipo e peso da métrica {$column}.");
            }
            $healthy = $metric['healthy_value'] ?? null;
            $critical = $metric['critical_value'] ?? null;
            if (! MetricDefinition::semScore($type) && (! in_array($direction, ['lower', 'higher'], true)
                || ! is_numeric($healthy) || ! is_numeric($critical)
                || abs((float) $healthy) > 9999999999 || abs((float) $critical) > 9999999999
                || ($direction === 'higher' && (float) $critical <= (float) $healthy)
                || ($direction === 'lower' && (float) $critical >= (float) $healthy))) {
                throw new InvalidArgumentException("Configure a direção e os valores saudável/crítico da métrica {$column}.");
            }
            $usedCodes[$code] = true;
            $definitions[$column] = [
                'type' => $type,
                'new' => [
                    'code' => $code, 'label' => $label, 'description' => $description, 'value_type' => $type,
                    'direction' => MetricDefinition::semScore($type) ? 'higher' : $direction,
                    'healthy_value' => MetricDefinition::semScore($type) ? 0 : (float) $healthy,
                    'critical_value' => MetricDefinition::semScore($type) ? 1 : (float) $critical,
                    'weight' => MetricDefinition::semScore($type) ? 0 : (float) $weight,
                ],
            ];
        }

        $records = [];
        $seen = [];
        $hasValue = false;
        foreach ($table['linhas'] as $index => $row) {
            $line = $index + 2;
            $context = [];
            foreach ($structure as $field => $column) {
                $context[$field] = $column === null ? '' : trim((string) ($row[$column] ?? ''));
                if (in_array($field, self::ESTRUTURA_OPCIONAL, true) && $context[$field] === '') {
                    $context[$field] = self::NAO_INFORMADO;
                }
                if ($context[$field] === '' || mb_strlen($context[$field]) > 255) {
                    throw new InvalidArgumentException("Linha {$line}: {$field} é obrigatório e deve ter até 255 caracteres.");
                }
            }
            $month = self::month($context['mes_ref']);
            if ($month === null) {
                throw new InvalidArgumentException("Linha {$line}: mes_ref deve ser AAAA-MM ou uma data no primeiro dia do mês.");
            }
            $financial = self::value($context['valor_mensal'], 'currency', $line, 'valor_mensal');
            $monthlyValue = $financial['value'] ?? null;
            if ($monthlyValue === null || $monthlyValue < 0 || $monthlyValue > 9999999999.99 || round($monthlyValue, 2) !== $monthlyValue) {
                throw new InvalidArgumentException("Linha {$line}: valor_mensal deve ser um valor financeiro não negativo, com até duas casas decimais.");
            }
            [$status, $cancelado, $inicio] = self::cadastro($row, $opcionais, $line);
            $key = $context['cliente_id'].':'.$month;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Linha {$line}: cliente e mês repetidos no mesmo arquivo.");
            }
            $seen[$key] = true;
            $values = [];
            foreach ($definitions as $column => $definition) {
                $values[$column] = self::value($row[$column] ?? null, $definition['type'], $line, $column);
                $hasValue = $hasValue || $values[$column] !== null;
            }
            $records[] = [
                'code' => $context['cliente_id'], 'month' => $month,
                'segment' => $context['segmento'], 'size' => $context['porte'], 'plan' => $context['plano'],
                'monthly_value' => $monthlyValue,
                'status' => $status, 'cancelled_month' => $cancelado, 'started_at' => $inicio,
                'values' => $values,
            ];
        }
        if (! $hasValue) {
            throw new InvalidArgumentException('Informe pelo menos um valor de métrica antes de importar.');
        }

        return [$structure, $definitions, $records];
    }

    /**
     * Situação (Ativo/Cancelado), mês de cancelamento e início do contrato, quando a planilha traz essas colunas.
     *
     * @param  array<string, string>  $opcionais
     * @return array{0: ?string, 1: ?string, 2: ?string} [status, AAAA-MM do cancelamento, AAAA-MM-DD do início]
     */
    private static function cadastro(array $row, array $opcionais, int $line): array
    {
        $ler = fn (string $campo): string => isset($opcionais[$campo]) ? trim((string) ($row[$opcionais[$campo]] ?? '')) : '';
        $situacao = Str::lower(Str::ascii($ler('situacao')));
        $status = match ($situacao) {
            '' => null, 'ativo' => 'Ativo', 'cancelado' => 'Cancelado',
            default => throw new InvalidArgumentException("Linha {$line}: situacao deve ser Ativo ou Cancelado."),
        };
        $mes = $ler('mes_cancelamento');
        $cancelado = $mes === '' ? null : (self::month($mes) ?? throw new InvalidArgumentException("Linha {$line}: mes_cancelamento deve ser AAAA-MM."));
        if ($status === 'Cancelado' && $cancelado === null) {
            throw new InvalidArgumentException("Linha {$line}: informe o mes_cancelamento de quem está Cancelado.");
        }
        if ($status === 'Ativo' && $cancelado !== null) {
            throw new InvalidArgumentException("Linha {$line}: cliente Ativo não pode ter mes_cancelamento.");
        }
        $inicio = $ler('inicio_contrato');
        $iniciado = $inicio === '' ? null : (self::data($inicio) ?? throw new InvalidArgumentException("Linha {$line}: inicio_contrato deve ser uma data (AAAA-MM-DD ou DD/MM/AAAA)."));

        return [$status, $cancelado, $iniciado];
    }

    /** Tipo escrito no dicionário ("Decimal (%)", "Inteiro 0-10", "Binario 0/1", "Data"...) para o tipo da métrica. */
    public static function tipoDoDicionario(string $texto): ?string
    {
        $t = Str::lower(Str::ascii(trim($texto)));

        return match (true) {
            $t === '' => null,
            str_starts_with($t, 'texto') => 'text',
            str_starts_with($t, 'data') => 'date',
            str_contains($t, 'binario') => 'binary',
            str_contains($t, '0-10') || str_starts_with($t, 'nota') => 'grade',
            str_contains($t, 'r$') || str_contains($t, 'monet') => 'currency',
            str_contains($t, '%') || str_contains($t, 'percent') => 'percentage',
            str_contains($t, 'inteiro') || str_contains($t, 'contagem') => 'integer',
            str_contains($t, 'decimal') || str_contains($t, 'numer') => 'decimal',
            default => null,
        };
    }

    private static function direcaoDoDicionario(string $texto): string
    {
        $t = Str::lower(Str::ascii(trim($texto)));

        return match (true) {
            str_contains($t, 'dimin') || str_contains($t, 'menor') || str_contains($t, 'cai') => 'lower',
            str_contains($t, 'aument') || str_contains($t, 'maior') || str_contains($t, 'sobe') => 'higher',
            default => '',
        };
    }

    private static function numeroDoDicionario(string $texto): float|string
    {
        $t = str_replace(',', '.', trim($texto));

        return is_numeric($t) ? (float) $t : '';
    }

    /** Data válida em AAAA-MM-DD ou DD/MM/AAAA, devolvida como AAAA-MM-DD. */
    private static function data(string $value): ?string
    {
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private static function month(string $value): ?string
    {
        foreach (['Y-m', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value
                && ($format === 'Y-m' || $date->format('d') === '01')) {
                return $date->format('Y-m');
            }
        }

        return null;
    }

    private static function value(mixed $raw, string $type, int $line, string $column): ?array
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        if ($type === 'date') {
            $iso = self::data($value) ?? throw new InvalidArgumentException("Linha {$line}: {$column} deve ser uma data (AAAA-MM-DD ou DD/MM/AAAA).");

            return ['value' => null, 'text_value' => $iso];
        }
        if ($type === 'binary') {
            $sim = ['1', 'sim', 's', 'true'];
            $nao = ['0', 'nao', 'não', 'n', 'false'];
            $chave = mb_strtolower($value);
            in_array($chave, [...$sim, ...$nao], true) || throw new InvalidArgumentException("Linha {$line}: {$column} deve ser 0 ou 1.");

            return ['value' => in_array($chave, $sim, true) ? 1.0 : 0.0, 'text_value' => null];
        }
        if ($type === 'text') {
            if (mb_strlen($value) > 1000) {
                throw new InvalidArgumentException("Linha {$line}: {$column} deve ter até 1000 caracteres.");
            }

            return ['value' => null, 'text_value' => $value];
        }
        $normalized = $value;
        if ($type === 'currency') {
            $normalized = preg_replace('/^R\$\s*/', '', $normalized);
        }
        if ($type === 'percentage') {
            $normalized = preg_replace('/\s*%$/', '', $normalized);
        }
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }
        $normalized = trim($normalized);
        $pattern = $type === 'integer' ? '/^-?\d+$/' : '/^-?\d+(?:\.\d{1,4})?$/';
        if (! preg_match($pattern, $normalized) || abs((float) $normalized) > 9999999999.9999
            || ($type === 'percentage' && ((float) $normalized < 0 || (float) $normalized > 100))
            || ($type === 'grade' && ((float) $normalized < 0 || (float) $normalized > 10))) {
            throw new InvalidArgumentException("Linha {$line}: {$column} não corresponde ao tipo {$type}.");
        }

        return ['value' => (float) $normalized, 'text_value' => null];
    }

    private static function validarCabecalhos(array $headers): void
    {
        if (! $headers || in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
            throw new InvalidArgumentException('A planilha precisa ter cabeçalhos únicos e não vazios.');
        }
    }

    private static function normalizar(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value)));
    }
}
