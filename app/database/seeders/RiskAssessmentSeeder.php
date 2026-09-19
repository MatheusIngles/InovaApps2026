<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\RiskAssessment;
use App\Support\Risco;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RiskAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $customers = Customer::with(['metrics' => fn ($query) => $query->orderBy('reference_month'), 'npsResponses' => fn ($query) => $query->orderBy('reference_month')])->get();
            $results = [];

            foreach ($customers as $customer) {
                $metrics = $customer->metrics->filter(fn ($metric) => $customer->cancelled_at === null || $metric->reference_month->lt($customer->cancelled_at));
                $referenceMonth = $metrics->last()?->reference_month;

                if ($referenceMonth === null) {
                    continue;
                }

                $history = $metrics->map(fn ($metric): array => [
                    'chamados_abertos' => $metric->tickets_opened,
                    'chamados_reabertos' => $metric->tickets_reopened,
                    'pct_sla_cumprido' => $metric->sla_percentage,
                    'reclamacoes_formais' => $metric->formal_complaints,
                    'uso_plataforma_pct' => $metric->platform_usage_percentage,
                    'dias_atraso_pagamento' => $metric->payment_delay_days,
                    'reunioes_previstas' => $metric->meetings_expected,
                    'reunioes_realizadas' => $metric->meetings_completed,
                ])->values()->all();
                $nps = $customer->npsResponses->filter(fn ($response) => $response->reference_month->lte($referenceMonth))
                    ->map(fn ($response): array => ['respondeu' => $response->answered, 'nota_nps' => $response->score])
                    ->values()->all();

                $results[$customer->id] = Risco::calcular($history, $nps) + ['customer' => $customer, 'reference_month' => $referenceMonth];
            }

            $cancelled = array_filter($results, fn (array $result): bool => $result['customer']->status === 'Cancelado');

            foreach ($results as $customerId => $result) {
                $customer = $result['customer'];
                $similar = [];

                foreach ($cancelled as $otherId => $other) {
                    if ($otherId === $customerId || $other['customer']->cancelled_at->gt($result['reference_month'])) {
                        continue;
                    }

                    $similar[] = [
                        'codigo' => $other['customer']->external_code,
                        'nome' => $other['customer']->displayName(),
                        'mes_cancel' => $other['customer']->cancelled_at?->format('Y-m'),
                        'sim' => Risco::semelhanca($result['sev'], $other['sev']),
                    ];
                }

                usort($similar, fn (array $first, array $second): int => $second['sim'] <=> $first['sim']);

                RiskAssessment::updateOrCreate(
                    ['customer_id' => $customerId, 'reference_month' => $result['reference_month'], 'model_version' => 'rules-v1'],
                    [
                        'health_score' => $result['score'],
                        'risk_probability' => null,
                        'priority_score' => round($result['score'] / 100 * (float) $customer->monthly_value, 2),
                        'expected_revenue_at_risk' => null,
                        'confidence' => null,
                        'signals_json' => ['evidence' => $result['sinais'], 'severity' => $result['sev'], 'similar' => array_slice($similar, 0, 3)],
                        'recommended_action_json' => null,
                        'calculated_at' => now(),
                    ],
                );
            }
        });
    }
}
