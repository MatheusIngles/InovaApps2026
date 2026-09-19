<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpisWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $ativas = Empresa::ativas();
        $risco = $ativas->where('score', '>=', 40);
        $perdida = Empresa::where('status', 'Cancelado')->sum('valor');

        return [
            Stat::make('Clientes ativos', $ativas->count())
                ->description(Empresa::where('status', 'Cancelado')->count().' cancelaram no período'),
            Stat::make('Receita mensal ativa', Empresa::brl($ativas->sum('valor')))->description('Contratos recorrentes'),
            Stat::make('Risco alto ou crítico', $risco->count())
                ->description(Empresa::brl($risco->sum('valor')).'/mês em jogo')->color('danger'),
            Stat::make('Receita já perdida', Empresa::brl($perdida))->description(Empresa::brl($perdida * 12).' ao ano'),
        ];
    }
}
