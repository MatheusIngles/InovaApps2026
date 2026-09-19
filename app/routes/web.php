<?php

use App\Filament\Pages\Planilha;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Http\Middleware\SetCompanyContext;
use App\Jobs\GerarRelatorioEmpresaJob;
use App\Models\Customer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Página principal: empresa nova (sem dados) -> planilha inicial; com dados -> lista de empresas. Visitantes -> login.
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect('/login');
    }

    return redirect(Customer::exists() ? EmpresaResource::getUrl() : Planilha::getUrl());
})->middleware(['web', SetCompanyContext::class]);

Route::get('/relatorios/{arquivo}/baixar', function (string $arquivo) {
    $user = auth()->user();
    $caminho = GerarRelatorioEmpresaJob::caminho($user->company_id, $user->id, $arquivo);

    abort_unless(Storage::disk('local')->exists($caminho), 404);

    return Storage::disk('local')->download($caminho, 'relatorio.pdf');
})->middleware(['web', 'auth', SetCompanyContext::class])->whereUuid('arquivo')->name('relatorios.download');
