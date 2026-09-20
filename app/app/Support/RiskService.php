<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Customer;
use App\Models\MetricDefinition;
use App\Models\RiskAssessment;
use App\Support\Metricas\MetricRisk;
use App\Support\Notificacoes\NotificacaoService;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

/** Recalcula as avaliações de risco de uma empresa com os pesos e limiares configurados por ela. */
class RiskService
{
    public static function recalcular(Company $company): int
    {
        return app(CompanyContext::class)->within($company, function () use ($company): int {
            return DB::transaction(function () use ($company): int {
                $pesos = $company->pesos();
                $limiares = $company->limiares();
                $definitions = $company->metricDefinitions()->get();
                $enabledDefinitionIds = $definitions->filter(fn ($definition) => $definition->enabled && ! MetricDefinition::semScore($definition->value_type) && (float) $definition->weight > 0)->pluck('id');
                $customers = Customer::with(['metrics' => fn ($q) => $q->orderBy('reference_month'), 'npsResponses' => fn ($q) => $q->orderBy('reference_month'), 'metricValues' => fn ($q) => $q->orderBy('reference_month'), 'periods' => fn ($q) => $q->orderBy('reference_month')])->get();
                $results = [];

                foreach ($customers as $customer) {
                    $metrics = $customer->metrics->filter(fn ($m) => $customer->cancelled_at === null || $m->reference_month->lt($customer->cancelled_at));
                    $values = $customer->metricValues->filter(fn ($value) => $enabledDefinitionIds->contains($value->metric_definition_id)
                        && $value->value !== null
                        && ($customer->cancelled_at === null || $value->reference_month->lt($customer->cancelled_at)));
                    $referenceMonth = $metrics->last()?->reference_month;
                    $latestCustomMonth = $values->last()?->reference_month;
                    if ($latestCustomMonth !== null && ($referenceMonth === null || $latestCustomMonth->gt($referenceMonth))) {
                        $referenceMonth = $latestCustomMonth;
                    }
                    $latestPeriod = $customer->periods->filter(fn ($period) => $customer->cancelled_at === null || $period->reference_month->lt($customer->cancelled_at))
                        ->last()?->reference_month;
                    if ($latestPeriod !== null && ($referenceMonth === null || $latestPeriod->gt($referenceMonth))) {
                        $referenceMonth = $latestPeriod;
                    }

                    if ($referenceMonth === null || ($metrics->isEmpty() && ! $values->contains(fn ($value) => $value->reference_month->gte($referenceMonth->copy()->subMonths(2)) && $value->reference_month->lte($referenceMonth)))) {
                        RiskAssessment::where('customer_id', $customer->id)->where('model_version', 'rules-v1')->delete();

                        continue;
                    }
                    RiskAssessment::where('customer_id', $customer->id)->where('model_version', 'rules-v1')
                        ->where('reference_month', '>', $referenceMonth)->delete();

                    $history = $metrics->map(fn ($m): array => [
                        'mes' => $m->reference_month->format('Y-m'),
                        'chamados_abertos' => $m->tickets_opened,
                        'chamados_reabertos' => $m->tickets_reopened,
                        'pct_sla_cumprido' => $m->sla_percentage,
                        'reclamacoes_formais' => $m->formal_complaints,
                        'uso_plataforma_pct' => $m->platform_usage_percentage,
                        'dias_atraso_pagamento' => $m->payment_delay_days,
                        'reunioes_previstas' => $m->meetings_expected,
                        'reunioes_realizadas' => $m->meetings_completed,
                    ])->values()->all();
                    $nps = $customer->npsResponses->filter(fn ($r) => $r->reference_month->lte($referenceMonth))
                        ->map(fn ($r): array => ['mes' => $r->reference_month->format('Y-m'), 'respondeu' => $r->answered, 'nota_nps' => $r->score])->values()->all();

                    $results[$customer->id] = MetricRisk::calcular($history, $nps, $pesos, $limiares, $definitions, $values, $referenceMonth)
                        + ['customer' => $customer, 'reference_month' => $referenceMonth, 'has_base' => $metrics->isNotEmpty()];
                }

                $cancelled = array_filter($results, fn (array $r): bool => $r['customer']->status === 'Cancelado');
                $vector = fn (array $r): array => ($r['has_base'] ? array_combine(array_keys(Risco::PESOS), $r['sev']) : [])
                    + $r['custom_severity'];

                foreach ($results as $customerId => $result) {
                    $customer = $result['customer'];
                    $similar = [];

                    foreach ($cancelled as $otherId => $other) {
                        if ($otherId === $customerId || $other['customer']->cancelled_at?->gt($result['reference_month'])) { // sem data de saída: compara como perfil
                            continue;
                        }

                        $currentVector = $vector($result);
                        $otherVector = $vector($other);
                        $sharedKeys = array_keys(array_intersect_key($currentVector, $otherVector));
                        if (! $sharedKeys) {
                            continue;
                        }

                        $similar[] = [
                            'codigo' => $other['customer']->external_code,
                            'nome' => $other['customer']->displayName(),
                            'mes_cancel' => $other['customer']->cancelled_at?->format('Y-m'),
                            'sim' => Risco::semelhanca(
                                array_map(fn ($key) => $currentVector[$key], $sharedKeys),
                                array_map(fn ($key) => $otherVector[$key], $sharedKeys),
                            ),
                        ];
                    }

                    usort($similar, fn (array $a, array $b): int => $b['sim'] <=> $a['sim']);

                    // capturado antes do upsert: é o estado anterior real quando o mês é novo, e o próprio mês
                    // atual (ainda não atualizado) quando o recálculo é só por mudança de peso/limiar.
                    $anterior = RiskAssessment::where('customer_id', $customerId)->where('model_version', 'rules-v1')
                        ->orderByDesc('reference_month')->first();

                    RiskAssessment::updateOrCreate(
                        ['customer_id' => $customerId, 'reference_month' => $result['reference_month'], 'model_version' => 'rules-v1'],
                        [
                            'health_score' => $result['score'],
                            'risk_probability' => null,
                            'exposure_indicator' => round($result['score'] / 100 * (float) $customer->monthly_value, 2),
                            'expected_revenue_at_risk' => null,
                            'confidence' => null,
                            'signals_json' => ['evidence' => $result['sinais'], 'severity' => $result['sev'], 'custom_severity' => $result['custom_severity'], 'contributions' => $result['contributions'], 'similar' => array_slice($similar, 0, 3)],
                            'recommended_action_json' => null,
                            'calculated_at' => now(),
                        ],
                    );

                    NotificacaoService::avaliar($company, $customer, $anterior, $result, $limiares);
                }

                return count($results);
            });
        });
    }
}
