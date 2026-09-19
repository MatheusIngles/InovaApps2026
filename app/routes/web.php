<?php

use App\Filament\Pages\Planilha;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Http\Middleware\SetCompanyContext;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;

// Página principal: empresa nova (sem dados) -> planilha inicial; com dados -> lista de empresas. Visitantes -> login.
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect('/login');
    }

    return redirect(Customer::exists() ? EmpresaResource::getUrl() : Planilha::getUrl());
})->middleware(['web', SetCompanyContext::class]);
