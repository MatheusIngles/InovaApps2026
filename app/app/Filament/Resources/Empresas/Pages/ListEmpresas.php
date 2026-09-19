<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\ListRecords;

class ListEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    public function getSubheading(): string
    {
        return 'Score = soma ponderada de 8 sinais dos últimos meses, não probabilidade de cancelamento. Exposição = score ÷ 100 × contrato mensal; serve para ordenar, não prevê perda. Ativas primeiro; canceladas ao final.';
    }
}
