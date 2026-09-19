<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;

/** Dashboard em /painel: aparece após a lista de empresas. */
class Painel extends Dashboard
{
    protected static string $routePath = '/painel';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?int $navigationSort = 2;
}
