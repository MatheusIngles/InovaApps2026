<?php

namespace App\Filament\Widgets;

use App\Models\CustomerMetric;
use App\Models\RiskAssessment;
use App\Support\Tenancy\CompanyContext;
use Filament\Widgets\ChartWidget;

class TendenciaChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Uso da plataforma × SLA cumprido';

    /** Altura reservada enquanto o gráfico carrega, para a página não pular. */
    protected ?string $placeholderHeight = '22rem';

    protected ?string $description = 'Média, por mês, dos percentuais informados para clientes atualmente ativos; SLA ausente não entra na média.';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return app(CompanyContext::class)->current()->hasLegacyMetrics() ? $this->heading : 'Atenção média por mês';
    }

    public function getDescription(): ?string
    {
        return app(CompanyContext::class)->current()->hasLegacyMetrics()
            ? $this->description
            : 'Média das avaliações calculadas com as métricas disponíveis em cada mês.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        if (! app(CompanyContext::class)->current()->hasLegacyMetrics()) {
            $months = RiskAssessment::query()->join('customers', 'customers.id', '=', 'risk_assessments.customer_id')
                ->where('customers.company_id', app(CompanyContext::class)->id())
                ->where('customers.status', 'Ativo')->where('model_version', 'rules-v1')
                ->selectRaw('reference_month, AVG(health_score) as score')
                ->groupBy('reference_month')->orderBy('reference_month')->get();
            $color = app(CompanyContext::class)->current()->tema()['primary'];

            return [
                'labels' => $months->pluck('reference_month')->map(fn ($date) => substr($date, 0, 7))->all(),
                'datasets' => [['label' => 'Atenção', 'data' => $months->map(fn ($row) => round($row->score))->all(), 'borderColor' => $color, 'backgroundColor' => $color, 'tension' => .3]],
            ];
        }

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
