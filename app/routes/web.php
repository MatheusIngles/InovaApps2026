<?php

use App\Filament\Pages\Planilha;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Http\Middleware\SetCompanyContext;
use App\Jobs\GerarRelatorioEmpresaJob;
use App\Models\Customer;
use App\Support\Import\DynamicImportService;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Página principal: empresa nova (sem dados) -> planilha inicial; com dados -> lista de empresas. Visitantes -> login.
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect('/login');
    }

    return redirect(Customer::exists() ? EmpresaResource::getUrl() : Planilha::getUrl());
})->middleware(['web', SetCompanyContext::class]);

Route::get('/modelo/planilha', function () {
    $company = app(CompanyContext::class)->current();

    return response()->streamDownload(
        fn () => print (DynamicImportService::csvModelo()),
        'seer-modelo-livre-'.$company->slug.'.csv',
        ['Content-Type' => 'text/csv; charset=UTF-8'],
    );
})->middleware(['web', 'auth', SetCompanyContext::class])->name('planilha.modelo');

Route::get('/modelo/planilha.xlsx', function () {
    $company = app(CompanyContext::class)->current();

    return response()->download(DynamicImportService::xlsxModelo($company), 'seer-modelo-'.$company->slug.'.xlsx')->deleteFileAfterSend();
})->middleware(['web', 'auth', SetCompanyContext::class])->name('planilha.modelo.xlsx');

Route::get('/relatorios/{arquivo}/baixar', function (string $arquivo) {
    $user = auth()->user();
    $caminho = GerarRelatorioEmpresaJob::caminho($user->company_id, $user->id, $arquivo);

    abort_unless(Storage::disk('local')->exists($caminho), 404);

    return Storage::disk('local')->download($caminho, 'relatorio.pdf');
})->middleware(['web', 'auth', SetCompanyContext::class])->whereUuid('arquivo')->name('relatorios.download');

// Voz neural do modo conversa do assistente (Edge TTS, pacote Python `edge-tts`). Sem ela, o navegador lê com a voz local.
Route::post('/assistente/voz', function (Request $request) {
    $texto = trim($request->validate(['texto' => ['required', 'string', 'max:1500']])['texto']);
    $arquivo = tempnam(sys_get_temp_dir(), 'voz').'.mp3';

    try {
        $r = Process::timeout(30)->run([config('llm.voz.python'), '-m', 'edge_tts', '--voice='.config('llm.voz.voz'), '--rate=+5%', '--text='.$texto, '--write-media='.$arquivo]);
        abort_unless($r->successful() && is_file($arquivo) && filesize($arquivo) > 0, 503);

        return response(file_get_contents($arquivo), 200, ['Content-Type' => 'audio/mpeg']);
    } finally {
        @unlink($arquivo);
    }
})->middleware(['web', 'auth', SetCompanyContext::class, 'throttle:20,1'])->name('assistente.voz');
