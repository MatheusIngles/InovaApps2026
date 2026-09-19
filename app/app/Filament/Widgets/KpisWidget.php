<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpisWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $ativas = Customer::ativas();
        $risco = $ativas->where('score', '>=', 40);
        $perdida = Customer::where('status', 'Cancelado')->sum('monthly_value');

        return [
            Stat::make('Clientes ativos', $ativas->count())
                ->description(Customer::where('status', 'Cancelado')->count().' cancelados na base importada'),
            Stat::make('Receita mensal ativa', Customer::brl($ativas->sum('valor')))->description('Soma dos contratos mensais dos ativos'),
            Stat::make('Atenção ≥ 40', $risco->count())
                ->description(Customer::brl($risco->sum('valor')).'/mês = soma dos contratos desse grupo; corte fixo de 40, não previsão de perda')->color('danger'),
            Stat::make('Contratos cancelados/mês', Customer::brl($perdida))
                ->description(Customer::brl($perdida * 12).' = valor mensal somado × 12; referência, não perda medida'),
        ];
    }
}
