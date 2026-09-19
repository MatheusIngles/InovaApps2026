<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Support\Tenancy\CompanyContext;
use Filament\Widgets\ChartWidget;

class SegmentosChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Atenção média por segmento';

    protected ?string $description = 'Média aritmética da atenção por regras (0–100) dos clientes ativos de cada segmento.';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $s = Customer::ativas()->groupBy('segmento')->map(fn ($customers) => $customers->avg('score'))->sortDesc();

        return [
            'labels' => $s->keys()->all(),
            'datasets' => [['label' => 'Atenção média', 'data' => $s->map(fn ($v) => round($v))->values()->all(), 'backgroundColor' => app(CompanyContext::class)->current()->tema()['primary']]],
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['min' => 0, 'max' => 100]]];
    }
}
