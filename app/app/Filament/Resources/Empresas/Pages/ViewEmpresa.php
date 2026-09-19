<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/** Perfil da empresa no estilo LinkedIn (banner, logo, sobre, destaques e clientes parecidos). */
class ViewEmpresa extends ViewRecord
{
    protected static string $resource = EmpresaResource::class;

    protected string $view = 'filament.pages.empresa';

    public function getTitle(): string|Htmlable
    {
        return $this->record->nome;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
