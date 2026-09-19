<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpisWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $ativas = Customer::ativas();
        $risco = $ativas->where('score', '>=', 40);
        $perdida = Customer::where('status', 'Cancelado')->sum('monthly_value');

        return [
            Stat::make('Clientes ativos', $ativas->count())
                ->description(Customer::where('status', 'Cancelado')->count().' cancelaram no período'),
            Stat::make('Receita mensal ativa', Customer::brl($ativas->sum('valor')))->description('Contratos recorrentes'),
            Stat::make('Score alto ou crítico', $risco->count())
                ->description(Customer::brl($risco->sum('valor')).'/mês em jogo')->color('danger'),
            Stat::make('Receita já perdida', Customer::brl($perdida))->description(Customer::brl($perdida * 12).' ao ano'),
        ];
    }
}
