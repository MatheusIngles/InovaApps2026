<?php

namespace App\Filament\Widgets;

use App\Models\CustomerMetric;
use Filament\Widgets\ChartWidget;

class TendenciaChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Uso da plataforma × SLA cumprido';

    protected ?string $description = 'Média mensal da carteira ativa (%)';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $meses = CustomerMetric::query()->join('customers', 'customers.id', '=', 'customer_metrics.customer_id')
            ->where('customers.status', 'Ativo')
            ->selectRaw('reference_month, AVG(platform_usage_percentage) as uso, AVG(sla_percentage) as sla')
            ->groupBy('reference_month')->orderBy('reference_month')->get();

        return [
            'labels' => $meses->pluck('reference_month')->map(fn ($date) => substr($date, 0, 7))->all(),
            'datasets' => [
                ['label' => 'Uso', 'data' => $meses->map(fn ($row) => round($row->uso))->all(), 'borderColor' => '#2563eb', 'backgroundColor' => '#2563eb', 'tension' => .3],
                ['label' => 'SLA', 'data' => $meses->map(fn ($row) => round($row->sla))->all(), 'borderColor' => '#93c5fd', 'backgroundColor' => '#93c5fd', 'tension' => .3],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'max' => 100]]];
    }
}
