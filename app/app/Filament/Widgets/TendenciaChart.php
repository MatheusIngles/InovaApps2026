<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
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
        $meses = Empresa::where('status', 'Ativo')->pluck('hist')->flatten(1)->groupBy('mes')->sortKeys();

        return [
            'labels' => $meses->keys()->all(),
            'datasets' => [
                ['label' => 'Uso', 'data' => $meses->map(fn ($g) => round($g->avg('uso')))->values()->all(), 'borderColor' => '#2563eb', 'backgroundColor' => '#2563eb', 'tension' => .3],
                ['label' => 'SLA', 'data' => $meses->map(fn ($g) => round($g->avg('sla')))->values()->all(), 'borderColor' => '#93c5fd', 'backgroundColor' => '#93c5fd', 'tension' => .3],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'max' => 100]]];
    }
}
