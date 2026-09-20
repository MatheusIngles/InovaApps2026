<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BacktestWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|array|null $columns = ['default' => 1, 'md' => 3];

    protected ?string $heading = 'Comparação exploratória do score';

    protected ?string $description = 'Médias aritméticas da atenção por regras (0–100). Cancelados: avaliação anterior à saída; ativos: última avaliação. Não valida previsão de churn.';

    protected function getStats(): array
    {
        $customers = Customer::dashboard()->get();
        $canc = $customers->where('status', 'Cancelado');
        $ativ = $customers->where('status', 'Ativo');
        $nc = $canc->count();
        $na = $ativ->count();

        return [
            Stat::make('Atenção média dos cancelados', round($canc->avg('score') ?? 0))->description('Média da atenção; vs '.round($ativ->avg('score') ?? 0).' nos ativos'),
            Stat::make('Cancelados com atenção ≥ 40', $canc->where('score', '>=', 40)->count()." de $nc")->color('success')
                ->description('Contagem com corte fixo de 40; última avaliação antes da saída'),
            Stat::make('Ativos com atenção ≥ 40', $ativ->where('score', '>=', 40)->count()." de $na")->color('warning')
                ->description('Contagem com corte fixo de 40; última avaliação disponível'),
        ];
    }
}
