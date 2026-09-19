<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
use Filament\Widgets\ChartWidget;

class NiveisChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Clientes ativos por nível de risco';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $n = Empresa::where('status', 'Ativo')->selectRaw('nivel, count(*) total')->groupBy('nivel')->pluck('total', 'nivel');
        $niveis = ['Crítico', 'Alto', 'Médio', 'Baixo'];

        return [
            'labels' => $niveis,
            'datasets' => [['data' => array_map(fn ($x) => $n[$x] ?? 0, $niveis), 'backgroundColor' => ['#dc2626', '#f97316', '#3b82f6', '#93c5fd']]],
        ];
    }
}
