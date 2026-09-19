<?php

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;

// Página principal: a tela da empresa prioritária (a primeira da fila). Visitantes vão para o login.
Route::get('/', function () {
    $empresa = auth()->check() ? Customer::ativas()->first() : null;

    return redirect($empresa ? EmpresaResource::getUrl('view', ['record' => $empresa]) : '/login');
})->middleware('web');
