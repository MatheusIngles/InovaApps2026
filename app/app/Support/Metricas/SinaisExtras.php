<?php

namespace App\Support\Metricas;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Chamados críticos, tempo médio de resolução e volume de chamados abertos vêm da planilha do desafio, mas não fazem
 * parte dos 8 sinais padrão. Aqui eles viram métricas da empresa (as mesmas do cadastro de métricas), com os valores
 * copiados das métricas mensais, e passam a entrar na atenção com o peso e as faixas definidos abaixo (ajustáveis em
 * Configurações › Métricas). Só ativa a coluna que tem dados; rodar de novo não duplica nem sobrescreve o que foi editado.
 */
class SinaisExtras
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: int, 4: int, 5: int}> coluna => [código, nome, descrição, saudável, crítico, peso] */
    public const PADRAO = [
        'tickets_critical' => ['chamados_criticos', 'Chamados críticos', 'Chamados com impacto em produção no mês. Piora quando aumenta.', 0, 3, 8],
        'avg_resolution_hours' => ['tempo_medio_resolucao_h', 'Tempo médio de resolução (h)', 'Tempo médio entre a abertura e a resolução dos chamados. Piora quando aumenta.', 22, 34, 12],
        'tickets_opened' => ['chamados_abertos', 'Volume de chamados abertos', 'Chamados abertos pelo cliente no mês. Piora quando aumenta.', 5, 14, 5],
    ];

    public static function codigo(string $coluna): ?string
    {
        return self::PADRAO[$coluna][0] ?? null;
    }

    /** @return int quantas métricas foram ativadas (criadas ou com os valores atualizados) */
    public static function ativar(Company $company): int
    {
        $ativadas = 0;

        foreach (self::PADRAO as $coluna => [$codigo, $nome, $descricao, $saudavel, $critico, $peso]) {
            $valores = DB::table('customer_metrics')->join('customers', 'customers.id', '=', 'customer_metrics.customer_id')
                ->where('customers.company_id', $company->id)->whereNotNull("customer_metrics.{$coluna}");

            if (! (clone $valores)->where("customer_metrics.{$coluna}", '>', 0)->exists()) {
                continue; // a coluna não veio na planilha (ou só tem zeros)
            }

            $definicao = $company->metricDefinitions()->withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $codigo],
                ['label' => $nome, 'description' => $descricao, 'value_type' => 'decimal', 'direction' => 'higher',
                    'healthy_value' => $saudavel, 'critical_value' => $critico, 'weight' => $peso, 'enabled' => true],
            );

            $agora = now();
            $valores->get(['customer_metrics.customer_id', 'customer_metrics.reference_month', "customer_metrics.{$coluna} as valor"])
                ->chunk(500)->each(fn ($lote) => DB::table('metric_values')->upsert(
                    $lote->map(fn ($linha): array => [
                        'company_id' => $company->id, 'customer_id' => $linha->customer_id, 'metric_definition_id' => $definicao->id,
                        'reference_month' => $linha->reference_month, 'value' => $linha->valor, 'created_at' => $agora, 'updated_at' => $agora,
                    ])->all(),
                    ['customer_id', 'metric_definition_id', 'reference_month'],
                    ['value', 'updated_at'],
                ));
            $ativadas++;
        }

        return $ativadas;
    }
}
