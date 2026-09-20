<?php

namespace App\Support\Import;

use App\Models\Company;
use App\Support\Metricas\SinaisExtras;
use App\Support\RiskService;
use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Motor de importação: aplica o mapeamento de colunas da empresa às linhas da planilha (cada linha = cliente × mês),
 * grava clientes, métricas mensais e NPS (upsert, então reimportar não duplica) e recalcula o risco da empresa.
 */
class ImportService
{
    /** campo canônico => [rótulo, apelidos aceitos na sugestão automática de mapeamento] */
    public const CAMPOS = [
        'cliente_id' => ['Código do cliente', ['codigo', 'cliente', 'id', 'idcliente', 'customer', 'customerid']],
        'mes_ref' => ['Mês de referência', ['mes', 'periodo', 'competencia', 'data', 'month']],
        'segmento' => ['Segmento', ['setor', 'segment']],
        'porte' => ['Porte', ['tamanho', 'size']],
        'plano' => ['Plano', ['pacote', 'plan']],
        'valor_mensal' => ['Valor mensal', ['valor', 'mensalidade', 'receita', 'mrr']],
        'sla_contratado_h' => ['SLA contratado (h)', ['sla', 'slacontratado']],
        'inicio_contrato' => ['Início do contrato', ['inicio', 'datainicio', 'contrato']],
        'situacao' => ['Situação (Ativo/Cancelado)', ['status', 'situacaocliente']],
        'mes_cancelamento' => ['Mês do cancelamento', ['cancelamento', 'datacancelamento', 'canceladoem']],
        'chamados_abertos' => ['Chamados abertos', ['chamados', 'tickets', 'abertos']],
        'chamados_criticos' => ['Chamados críticos', ['criticos']],
        'chamados_reabertos' => ['Chamados reabertos', ['reabertos']],
        'chamados_dentro_sla' => ['Chamados dentro do SLA', ['dentrosla']],
        'pct_sla_cumprido' => ['% de SLA cumprido', ['slacumprido', 'pctsla', 'slapct']],
        'tempo_medio_resolucao_h' => ['Tempo médio de resolução (h)', ['tempomedio', 'resolucao', 'tmr']],
        'reclamacoes_formais' => ['Reclamações formais', ['reclamacoes']],
        'uso_plataforma_pct' => ['% de uso da plataforma', ['uso', 'usoplataforma', 'engajamento']],
        'dias_atraso_pagamento' => ['Dias de atraso de pagamento', ['atraso', 'diasatraso']],
        'reunioes_previstas' => ['Reuniões previstas', ['reunioesplanejadas', 'previstas']],
        'reunioes_realizadas' => ['Reuniões realizadas', ['realizadas']],
        'respondeu' => ['Respondeu à pesquisa (1/0)', ['respondeunps', 'respondida']],
        'nota_nps' => ['Nota NPS (0-10)', ['nps', 'nota']],
        'classificacao_nps' => ['Classificação NPS', ['classificacao']],
    ];

    private const METRICAS = ['chamados_abertos', 'chamados_criticos', 'chamados_reabertos', 'chamados_dentro_sla', 'pct_sla_cumprido',
        'tempo_medio_resolucao_h', 'reclamacoes_formais', 'uso_plataforma_pct', 'dias_atraso_pagamento', 'reunioes_previstas', 'reunioes_realizadas'];

    /** Sugere campo → cabeçalho: usa o mapeamento salvo da empresa quando o cabeçalho ainda existe; senão compara nomes/apelidos. */
    public static function sugerirMapeamento(array $cabecalhos, array $salvo = []): array
    {
        $norm = fn ($t) => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $t)));
        $porNorm = [];
        foreach ($cabecalhos as $h) {
            $porNorm[$norm($h)] ??= $h;
        }

        $mapa = [];
        foreach (self::CAMPOS as $campo => [$rotulo, $apelidos]) {
            $mapa[$campo] = isset($salvo[$campo]) && in_array($salvo[$campo], $cabecalhos, true)
                ? $salvo[$campo]
                : ($porNorm[$norm($campo)] ?? $porNorm[$norm($rotulo)] ?? collect($apelidos)->map(fn ($a) => $porNorm[$norm($a)] ?? null)->filter()->first());
        }

        return $mapa;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  array<string, string|null>  $mapa  campo canônico → cabeçalho da planilha
     * @return array{clientes: int, meses: int, nps: int, ignoradas: int}
     */
    public static function importar(Company $company, array $linhas, array $mapa, array $customColumns = []): array
    {
        $mapa = array_filter($mapa, fn ($h) => is_string($h) && $h !== '');

        if (! isset($mapa['cliente_id'])) {
            throw new InvalidArgumentException('Mapeie a coluna do código do cliente.');
        }
        if (! $linhas) {
            throw new InvalidArgumentException('A planilha não tem linhas de dados.');
        }

        $stats = DB::transaction(function () use ($company, $linhas, $mapa, $customColumns): array {
            return app(CompanyContext::class)->within($company, fn () => self::gravar($company, $linhas, $mapa, $customColumns));
        });

        $company->update(['imported_at' => now(), 'column_mapping' => $mapa]);
        SinaisExtras::ativar($company); // chamados críticos, tempo de resolução e volume passam a entrar na atenção
        Backtest::invalidar($company);
        RiskService::recalcular($company);

        try {
            app(CompanyContext::class)->within($company, fn () => CompanyConfig::aplicarBaseDosDados($company->fresh()));
        } catch (\Throwable $e) {
            report($e); // a configuração base é um bônus: nunca derruba a importação
        }

        return $stats;
    }

    private static function gravar(Company $company, array $linhas, array $mapa, array $customColumns): array
    {
        $v = fn (array $linha, string $campo) => isset($mapa[$campo]) && array_key_exists($mapa[$campo], $linha) && $linha[$mapa[$campo]] !== '' ? $linha[$mapa[$campo]] : null;
        $clientes = [];
        $metricas = [];
        $pesquisas = [];
        $ignoradas = 0;

        foreach ($linhas as $linha) {
            $codigo = trim((string) $v($linha, 'cliente_id'));
            if ($codigo === '') {
                $ignoradas++;

                continue;
            }

            $c = &$clientes[$codigo];
            foreach (['segmento', 'porte', 'plano', 'valor_mensal', 'sla_contratado_h', 'inicio_contrato', 'situacao', 'mes_cancelamento'] as $campo) {
                $c[$campo] ??= $v($linha, $campo); // primeiro valor preenchido do cliente
            }
            unset($c);

            $rawMes = $v($linha, 'mes_ref');
            if ($rawMes === null) {
                continue; // linha só de cadastro
            }
            if (($mes = self::mes($rawMes)) === null) {
                $ignoradas++;

                continue;
            }

            if (collect(self::METRICAS)->contains(fn ($m) => $v($linha, $m) !== null)) {
                $metricas[$codigo][$mes] = $linha;
            }
            if ($v($linha, 'respondeu') !== null || $v($linha, 'nota_nps') !== null) {
                $pesquisas[$codigo][$mes] = $linha;
            }
        }

        $agora = now();
        DB::table('customers')->upsert(array_map(function ($codigo, $c) use ($company, $agora, $metricas) {
            $situacao = Str::lower(Str::ascii((string) ($c['situacao'] ?? '')));
            $inicio = self::data($c['inicio_contrato'] ?? null) ?? (isset($metricas[$codigo]) ? array_key_first($metricas[$codigo]) : null) ?? $agora->toDateString();

            return [
                'company_id' => $company->id, 'external_code' => $codigo,
                'segment' => $c['segmento'] ?? 'Não informado', 'size' => $c['porte'] ?? 'Não informado', 'plan' => $c['plano'] ?? 'Não informado',
                'monthly_value' => self::numero($c['valor_mensal'] ?? null) ?? 0, 'contracted_sla_hours' => (int) (self::numero($c['sla_contratado_h'] ?? null) ?? 0),
                'contract_started_at' => $inicio, 'status' => str_starts_with($situacao, 'cancel') ? 'Cancelado' : 'Ativo',
                'cancelled_at' => str_starts_with($situacao, 'cancel') ? (self::mes($c['mes_cancelamento'] ?? null) ?? self::mesSeguinte($metricas[$codigo] ?? [])) : null,
                'created_at' => $agora, 'updated_at' => $agora,
            ];
        }, array_keys($clientes), $clientes), ['company_id', 'external_code'], ['segment', 'size', 'plan', 'monthly_value', 'contracted_sla_hours', 'contract_started_at', 'status', 'cancelled_at', 'updated_at']);

        $ids = DB::table('customers')->where('company_id', $company->id)->pluck('id', 'external_code');
        $linhasMetricas = [];
        $linhasNps = [];

        foreach ($metricas as $codigo => $porMes) {
            foreach ($porMes as $mes => $linha) {
                $n = fn ($campo, $padrao = 0) => self::numero($v($linha, $campo)) ?? $padrao;
                $linhasMetricas[] = [
                    'customer_id' => $ids[$codigo], 'reference_month' => $mes,
                    'tickets_opened' => (int) $n('chamados_abertos'), 'tickets_critical' => (int) $n('chamados_criticos'), 'tickets_reopened' => (int) $n('chamados_reabertos'),
                    'tickets_within_sla' => (int) $n('chamados_dentro_sla'), 'sla_percentage' => self::numero($v($linha, 'pct_sla_cumprido')), // vazio = sem chamados
                    'avg_resolution_hours' => $n('tempo_medio_resolucao_h'), 'formal_complaints' => (int) $n('reclamacoes_formais'),
                    'platform_usage_percentage' => $n('uso_plataforma_pct', 100), // sem dado de uso = não penaliza
                    'payment_delay_days' => (int) $n('dias_atraso_pagamento'), 'meetings_expected' => (int) $n('reunioes_previstas'), 'meetings_completed' => (int) $n('reunioes_realizadas'),
                    'created_at' => $agora, 'updated_at' => $agora,
                ];
            }
        }
        foreach ($pesquisas as $codigo => $porMes) {
            foreach ($porMes as $mes => $linha) {
                $nota = self::numero($v($linha, 'nota_nps'));
                $respondeu = $v($linha, 'respondeu');
                $linhasNps[] = [
                    'customer_id' => $ids[$codigo], 'reference_month' => $mes,
                    'answered' => (int) ($respondeu !== null ? (bool) self::numero($respondeu) : $nota !== null), 'score' => $nota === null ? null : (int) $nota,
                    'classification' => $v($linha, 'classificacao_nps'), 'created_at' => $agora, 'updated_at' => $agora,
                ];
            }
        }

        foreach (array_chunk($linhasMetricas, 250) as $lote) {
            DB::table('customer_metrics')->upsert($lote, ['customer_id', 'reference_month'], ['tickets_opened', 'tickets_critical', 'tickets_reopened', 'tickets_within_sla', 'sla_percentage',
                'avg_resolution_hours', 'formal_complaints', 'platform_usage_percentage', 'payment_delay_days', 'meetings_expected', 'meetings_completed', 'updated_at']);
        }
        foreach (array_chunk($linhasNps, 250) as $lote) {
            DB::table('customer_nps')->upsert($lote, ['customer_id', 'reference_month'], ['answered', 'score', 'classification', 'updated_at']);
        }

        $customCount = 0;
        if ($customColumns) {
            $definitions = $company->metricDefinitions()->whereIn('code', array_keys($customColumns))->pluck('id', 'code');
            if ($definitions->count() !== count($customColumns)) {
                throw new InvalidArgumentException('Uma métrica do modelo não pertence a esta empresa.');
            }
            $customRows = [];
            $emptyValues = [];
            foreach ($linhas as $row) {
                $code = trim((string) $v($row, 'cliente_id'));
                $month = self::mes($v($row, 'mes_ref'));
                foreach ($customColumns as $metricCode => $column) {
                    $raw = $row[$column] ?? null;
                    if ($raw === null || $raw === '') {
                        $emptyValues[$ids[$code]][$month][] = $definitions[$metricCode];

                        continue;
                    }
                    $value = self::numero($raw);
                    if ($value === null) {
                        throw new InvalidArgumentException("Valor inválido na métrica {$metricCode}.");
                    }
                    $customRows[] = [
                        'company_id' => $company->id, 'customer_id' => $ids[$code], 'metric_definition_id' => $definitions[$metricCode],
                        'reference_month' => $month, 'value' => $value, 'created_at' => $agora, 'updated_at' => $agora,
                    ];
                }
            }
            foreach ($emptyValues as $customerId => $months) {
                foreach ($months as $month => $definitionIds) {
                    DB::table('metric_values')->where('customer_id', $customerId)->where('reference_month', $month)
                        ->whereIn('metric_definition_id', $definitionIds)->delete();
                }
            }
            foreach (array_chunk($customRows, 250) as $batch) {
                DB::table('metric_values')->upsert($batch, ['customer_id', 'metric_definition_id', 'reference_month'], ['value', 'updated_at']);
            }
            $customCount = count($customRows);
        }

        $stats = ['clientes' => count($clientes), 'meses' => count($linhasMetricas), 'nps' => count($linhasNps), 'ignoradas' => $ignoradas];

        return $customColumns ? $stats + ['valores_metricas' => $customCount] : $stats;
    }

    /** Cancelado sem mês de saída informado: assume o mês seguinte ao último com métricas (sem métricas = sem data). */
    private static function mesSeguinte(array $porMes): ?string
    {
        return $porMes ? Carbon::parse(max(array_keys($porMes)))->addMonth()->toDateString() : null;
    }

    /** Aceita 1234.5, "1234,5" e "1.234,5"; texto não numérico vira null. */
    private static function numero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_string($valor)) {
            $valor = trim(str_replace(['R$', '%', ' '], '', $valor));
            $valor = str_contains($valor, ',') ? str_replace(',', '.', str_replace('.', '', $valor)) : $valor;
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    /** Normaliza para o 1º dia do mês: "2025-03", "2025-03-15", "03/2025", datas. Inválido = null. */
    private static function mes(mixed $valor): ?string
    {
        $d = self::data($valor);

        return $d ? substr($d, 0, 7).'-01' : null;
    }

    private static function data(mixed $valor): ?string
    {
        $t = trim((string) $valor);
        $ymd = null;

        if ($t === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})(?:-(\d{1,2}))?/', $t, $m)) {
            $ymd = [$m[1], $m[2], $m[3] ?? 1];
        } elseif (preg_match('/^(\d{1,2})\/(\d{4})$/', $t, $m)) {
            $ymd = [$m[2], $m[1], 1];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $t, $m)) {
            $ymd = [$m[3], $m[2], $m[1]];
        } else {
            return rescue(fn () => Carbon::parse($t)->toDateString(), null, false);
        }

        return checkdate((int) $ymd[1], (int) $ymd[2], (int) $ymd[0]) ? sprintf('%04d-%02d-%02d', ...$ymd) : null;
    }
}
