<?php

namespace App\Filament\Widgets;

use App\Models\CustomerMetric;
use App\Support\Tenancy\CompanyContext;
use Filament\Widgets\ChartWidget;

class TendenciaChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Uso da plataforma × SLA cumprido';

    protected ?string $description = 'Média, por mês, dos percentuais informados para clientes atualmente ativos; SLA ausente não entra na média.';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $meses = CustomerMetric::query()->join('customers', 'customers.id', '=', 'customer_metrics.customer_id')
            ->where('customers.status', 'Ativo')->where('customers.company_id', app(CompanyContext::class)->id())
            ->selectRaw('reference_month, AVG(platform_usage_percentage) as uso, AVG(sla_percentage) as sla')
            ->groupBy('reference_month')->orderBy('reference_month')->get();

        $tema = app(CompanyContext::class)->current()->tema();

        return [
            'labels' => $meses->pluck('reference_month')->map(fn ($date) => substr($date, 0, 7))->all(),
            'datasets' => [
                ['label' => 'Uso', 'data' => $meses->map(fn ($row) => round($row->uso))->all(), 'borderColor' => $tema['primary'], 'backgroundColor' => $tema['primary'], 'tension' => .3],
                ['label' => 'SLA', 'data' => $meses->map(fn ($row) => round($row->sla))->all(), 'borderColor' => $tema['secondary'], 'backgroundColor' => $tema['secondary'], 'tension' => .3],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'max' => 100]]];
    }
}
