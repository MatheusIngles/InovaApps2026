<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\ListRecords;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    public function getSubheading(): string
    {
        return 'Prioridade = risco × valor do contrato (regra de três, com reforço para risco alto); o equilíbrio é ajustável em Configurações. O score soma 8 sinais ponderados e não é probabilidade de cancelamento. Canceladas ao final.';
    }
}
