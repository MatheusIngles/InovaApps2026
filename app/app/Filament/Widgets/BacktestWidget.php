<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** O mesmo score aplicado aos 3 meses antes da saída dos cancelados: prova que separa quem sai de quem fica. */
class BacktestWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'O score separa quem sai de quem fica?';

    protected ?string $description = 'Backtest com os clientes que já cancelaram';

    protected function getStats(): array
    {
        $canc = Empresa::where('status', 'Cancelado');
        $ativ = Empresa::where('status', 'Ativo');
        $nc = (clone $canc)->count();
        $na = (clone $ativ)->count();

        return [
            Stat::make('Score médio dos cancelados', round((clone $canc)->avg('score')))->description('vs '.round((clone $ativ)->avg('score')).' nos ativos'),
            Stat::make('Cancelados com score ≥ 40', (clone $canc)->where('score', '>=', 40)->count()." de $nc")->color('success')
                ->description('teriam sido sinalizados a tempo'),
            Stat::make('Ativos com score ≥ 40', (clone $ativ)->where('score', '>=', 40)->count()." de $na")->color('warning')
                ->description('poucos alarmes falsos'),
        ];
    }
}
