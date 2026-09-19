<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\ChartWidget;

class NiveisChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Clientes ativos por nível de atenção';

    protected ?string $description = 'Contagem por faixas da atenção (0–100), conforme limites configurados; não é probabilidade.';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $n = Customer::ativas()->countBy('nivel');
        $niveis = ['Crítico', 'Alto', 'Médio', 'Baixo'];

        return [
            'labels' => $niveis,
            'datasets' => [['data' => array_map(fn ($x) => $n[$x] ?? 0, $niveis), 'backgroundColor' => ['#dc2626', '#f97316', '#3b82f6', '#93c5fd']]],
        ];
    }
}
