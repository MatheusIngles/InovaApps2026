<?php

use App\Filament\Pages\Planilha;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Http\Middleware\SetCompanyContext;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;

// Página principal: empresa nova (sem dados) -> planilha inicial; com dados -> empresa prioritária. Visitantes -> login.
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect('/login');
    }

    $empresa = Customer::ativas()->first();

    return redirect($empresa ? EmpresaResource::getUrl('view', ['record' => $empresa]) : Planilha::getUrl());
})->middleware(['web', SetCompanyContext::class]);
