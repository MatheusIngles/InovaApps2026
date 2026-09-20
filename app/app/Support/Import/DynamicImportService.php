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
    public static function sugerir(Company $company, array $headers, array $preview): array
    {
        self::validarCabecalhos($headers);
        $suggestions = ImportService::sugerirMapeamento($headers);
        $structure = array_intersect_key($suggestions, self::STRUCTURE);
        $used = array_filter($structure);
        $metrics = [];

        foreach ($headers as $header) {
            if (! in_array($header, $used, true)) {
                $metrics[] = self::sugerirMetrica($company, $header, $preview);
            }
        }

        return ['structure' => $structure, 'metrics' => $metrics];
    }

    public static function sugerirMetrica(Company $company, string $header, array $preview): array
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
            $type = 'text';
        }
        $code = Str::slug(Str::ascii($name), '_');
        if (! preg_match('/^[a-z]/', $code)) {
            $code = 'm_'.$code;
        }

        return [
            'column' => $header,
            'target' => $existing ? (string) $existing->id : 'new',
            'code' => substr($code, 0, 40),
            'label' => $existing?->label ?? $name,
            'description' => $existing?->description ?? '',
            'value_type' => $existing?->value_type ?? $type,
            'direction' => $existing?->direction ?? '',
            'healthy_value' => $existing?->healthy_value ?? '',
            'critical_value' => $existing?->critical_value ?? '',
            'weight' => $existing?->weight ?? ($type === 'text' ? 0 : 10),
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
                            'started' => min($record['month'], $customers[$code]['started'] ?? $record['month']),
                        ];
                    } else {
                        $customers[$code]['started'] = min($record['month'], $customers[$code]['started']);
                    }
                }
                $now = now();
                $customerRows = [];
                foreach ($customers as $code => $customer) {
                    $customerRows[] = [
                        'company_id' => $company->id, 'external_code' => $code,
                        'segment' => $customer['segment'], 'size' => $customer['size'], 'plan' => $customer['plan'],
                        'monthly_value' => $customer['monthly_value'], 'contracted_sla_hours' => 0,
                        'contract_started_at' => $customer['started'].'-01', 'status' => 'Ativo', 'cancelled_at' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($customerRows, 250) as $batch) {
                    DB::table('customers')->upsert($batch, ['company_id', 'external_code'], ['segment', 'size', 'plan', 'monthly_value', 'updated_at']);
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
        if (count($structure) !== count(self::STRUCTURE)
            || array_keys($structure) !== array_keys(self::STRUCTURE)
            || count(array_unique(array_values($structure))) !== count(self::STRUCTURE)) {
            throw new InvalidArgumentException('Confirme as seis colunas estruturais sem repetições.');
        }
        foreach ($structure as $column) {
            if (! in_array($column, $headers, true)) {
                throw new InvalidArgumentException('Uma coluna estrutural não foi encontrada na planilha.');
            }
        }
        $metricHeaders = array_values(array_diff($headers, array_values($structure)));
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
            if ($type !== 'text' && (! in_array($direction, ['lower', 'higher'], true)
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
                    'direction' => $type === 'text' ? 'higher' : $direction,
                    'healthy_value' => $type === 'text' ? 0 : (float) $healthy,
                    'critical_value' => $type === 'text' ? 1 : (float) $critical,
                    'weight' => $type === 'text' ? 0 : (float) $weight,
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
                $context[$field] = trim((string) ($row[$column] ?? ''));
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
                'values' => $values,
            ];
        }
        if (! $hasValue) {
            throw new InvalidArgumentException('Informe pelo menos um valor de métrica antes de importar.');
        }

        return [$structure, $definitions, $records];
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
            || ($type === 'percentage' && ((float) $normalized < 0 || (float) $normalized > 100))) {
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
