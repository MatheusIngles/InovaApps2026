<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/** Dashboard em /painel: visão geral, evidências do backtest e análise por segmento (abas). */
class Painel extends Dashboard
{
    protected static string $routePath = '/painel';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?int $navigationSort = 2;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Painel')->tabs([
                Tab::make('Visão geral')->schema([$this->getWidgetsContentComponent()]),
                Tab::make('Evidências')->schema([View::make('filament.components.painel-evidencias')]),
                Tab::make('Por segmento')->schema([View::make('filament.components.painel-segmentos')]),
            ])->persistTabInQueryString('aba')->columnSpanFull(),
        ]);
    }
}
