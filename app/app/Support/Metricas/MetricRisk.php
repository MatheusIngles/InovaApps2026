<?php

namespace App\Support\Metricas;

use App\Models\MetricDefinition;
use App\Support\Risco;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MetricRisk
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, array<string, mixed>>  $nps
     * @param  array<string, float|int>  $baseWeights
     * @param  Collection<int, MetricDefinition>  $definitions
     * @param  Collection<int, mixed>  $values
     */
    public static function calcular(
        array $history,
        array $nps,
        array $baseWeights,
        array $thresholds,
        Collection $definitions,
        Collection $values,
        CarbonInterface $referenceMonth,
    ): array {
        $effectiveBaseWeights = $history ? $baseWeights : [];
        $base = Risco::calcular($history, $nps, $effectiveBaseWeights, $thresholds);
        if (! $history) {
            $base['sev'] = array_fill(0, count(Risco::PESOS), 0.0);
        }
        $severity = array_combine(array_keys(Risco::PESOS), $base['sev']);
        $weights = $effectiveBaseWeights;
        $customEvidence = [];

        foreach ($definitions as $definition) {
            if (! $definition->enabled || $definition->value_type === 'text' || (float) $definition->weight <= 0) {
                continue;
            }

            $windowStart = $referenceMonth->copy()->startOfMonth()->subMonths(2);
            $observations = $values->filter(fn ($value) => $value->metric_definition_id === $definition->id && $value->value !== null
                && $value->reference_month->gte($windowStart)
                && $value->reference_month->lte($referenceMonth));

            if ($observations->isEmpty()) {
                continue;
            }

            $ordered = $observations->pluck('value')->map(fn ($value) => (float) $value)->sort()->values();
            $middle = intdiv($ordered->count(), 2);
            $current = $ordered->count() % 2
                ? $ordered[$middle]
                : ($ordered[$middle - 1] + $ordered[$middle]) / 2;
            $healthy = (float) $definition->healthy_value;
            $critical = (float) $definition->critical_value;
            $key = 'custom:'.$definition->code;
            $severity[$key] = max(0.0, min(1.0, ($current - $healthy) / ($critical - $healthy)));
            $weights[$key] = (float) $definition->weight;
            $customEvidence[$key] = [
                'k' => $key,
                'label' => $definition->label,
                'texto' => sprintf('%s: %.2f (referência saudável: %.2f; mediana de %d mês(es))', $definition->label, $current, $healthy, $ordered->count()),
                'acao' => 'Conversar com o cliente sobre '.mb_strtolower($definition->label).' e combinar um plano de recuperação.',
                'current' => $current,
                'baseline' => $healthy,
                'periods' => $ordered->count(),
            ];
        }

        if (! $customEvidence) {
            return $base + ['contributions' => null, 'custom_severity' => []];
        }

        $points = Risco::pontos($severity, $weights);
        $totalWeight = array_sum($weights);
        $evidence = [];

        foreach ($base['sinais'] as $signal) {
            $key = $signal['k'];
            $signal['pts'] = $points[$key];
            $signal['max'] = $totalWeight > 0 ? round($weights[$key] / $totalWeight * 100, 1) : 0;
            if ($signal['pts'] >= $signal['max'] * 0.35 && $signal['max'] > 0) {
                $evidence[] = $signal;
            }
        }
        foreach ($customEvidence as $key => $signal) {
            $signal['pts'] = $points[$key];
            $signal['max'] = round($weights[$key] / $totalWeight * 100, 1);
            if ($signal['pts'] >= $signal['max'] * 0.35) {
                $evidence[] = $signal;
            }
        }
        usort($evidence, fn (array $a, array $b): int => $b['pts'] <=> $a['pts']);

        $equalShare = 100 / count($weights);
        $contributions = [];
        foreach ($weights as $key => $weight) {
            $basePoints = round($severity[$key] * $equalShare, 1);
            $contributions[] = [
                'rotulo' => $customEvidence[$key]['label'] ?? Risco::ROTULOS[$key],
                'intensidade' => $severity[$key],
                'peso' => $weight,
                'base' => $basePoints,
                'equal_share' => $equalShare,
                'ajuste_prioridade' => round($points[$key] - $basePoints, 1),
                'pontos' => $points[$key],
            ];
        }
        $score = (int) round(array_sum($points));

        return [
            'score' => $score,
            'nivel' => Risco::nivel($score, $thresholds),
            'sinais' => $evidence,
            'sev' => $base['sev'],
            'custom_severity' => array_intersect_key($severity, $customEvidence),
            'contributions' => $contributions,
        ];
    }
}
