<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/** Dashboard em /painel: a raiz (/) leva para a tela da empresa. */
class Painel extends Dashboard
{
    protected static string $routePath = '/painel';

    protected static ?int $navigationSort = 1;
}
