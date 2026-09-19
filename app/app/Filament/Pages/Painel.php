<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/** Dashboard em /painel: aparece após a lista de empresas. */
class Painel extends Dashboard
{
    protected static string $routePath = '/painel';

    protected static ?int $navigationSort = 2;
}
