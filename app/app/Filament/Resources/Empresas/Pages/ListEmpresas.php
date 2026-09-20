<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\ListRecords;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    public function getSubheading(): string
    {
        return 'Ativos primeiro, primeiro quem já está em alerta e, dentro de cada grupo, pela combinação de atenção e valor do contrato (ajustada em Configurações). Abra um cliente para ver a origem da atenção.';
    }
}
