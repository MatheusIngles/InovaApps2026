<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
use Filament\Widgets\ChartWidget;

class SegmentosChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Risco médio por segmento';

    protected ?string $description = 'Score de 0 a 100, clientes ativos';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $s = Empresa::where('status', 'Ativo')->selectRaw('segmento, avg(score) media')->groupBy('segmento')->orderByDesc('media')->pluck('media', 'segmento');

        return [
            'labels' => $s->keys()->all(),
            'datasets' => [['label' => 'Score médio', 'data' => $s->map(fn ($v) => round($v))->values()->all(), 'backgroundColor' => '#2563eb']],
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['min' => 0, 'max' => 100]]];
    }
}
