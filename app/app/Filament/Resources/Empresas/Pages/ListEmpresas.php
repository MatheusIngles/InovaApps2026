<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\ListRecords;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    public function getSubheading(): string
    {
        return 'Prioridade = risco × valor do contrato (regra de três, com reforço para risco alto): quem mais pode custar à carteira vem primeiro. Canceladas ao final.';
    }
}
