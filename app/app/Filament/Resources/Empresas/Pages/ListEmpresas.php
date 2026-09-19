<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\ListRecords;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    public function getSubheading(): string
    {
        return 'Ativas primeiro, pela combinação de score e valor do contrato ajustada em Configurações. Abra um cliente para ver a origem do score.';
    }
}
