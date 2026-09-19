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

    protected ?string $description = 'Score por regras; esta comparação não valida uma previsão de churn.';

    protected function getStats(): array
    {
        $customers = Customer::dashboard()->get();
        $canc = $customers->where('status', 'Cancelado');
        $ativ = $customers->where('status', 'Ativo');
        $nc = $canc->count();
        $na = $ativ->count();

        return [
            Stat::make('Score médio dos cancelados', round($canc->avg('score') ?? 0))->description('vs '.round($ativ->avg('score') ?? 0).' nos ativos'),
            Stat::make('Cancelados com score ≥ 40', $canc->where('score', '>=', 40)->count()." de $nc")->color('success')
                ->description('na última avaliação anterior ao cancelamento'),
            Stat::make('Ativos com score ≥ 40', $ativ->where('score', '>=', 40)->count()." de $na")->color('warning')
                ->description('na última avaliação disponível'),
        ];
    }
}
