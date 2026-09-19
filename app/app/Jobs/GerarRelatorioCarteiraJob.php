<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Support\Relatorio\RelatorioService;
use App\Support\Tenancy\CompanyContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Gera o relatório geral da carteira em PDF fora da requisição e avisa só quem pediu. */
class GerarRelatorioCarteiraJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $companyId, public int $userId, public string $arquivoId) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || $user->company_id !== $this->companyId) {
            return;
        }

        try {
            $company = Company::findOrFail($this->companyId);
            $pdf = app(CompanyContext::class)->within($company, fn (): string => RelatorioService::carteira($company));

            if (! Storage::disk('local')->put(GerarRelatorioEmpresaJob::caminho($this->companyId, $this->userId, $this->arquivoId), $pdf)) {
                throw new \RuntimeException('Não foi possível salvar o PDF.');
            }

            Notification::make()->title('Relatório de evidências pronto')
                ->body('O relatório geral está disponível para download.')
                ->success()->actions([
                    Action::make('baixar')->label('Baixar PDF')
                        ->url(route('relatorios.download', ['arquivo' => $this->arquivoId]))->markAsRead(),
                ])->sendToDatabase($user);
        } catch (Throwable $e) {
            Log::error('Falha ao gerar relatório de evidências.', ['company_id' => $this->companyId, 'exception' => $e]);
            Notification::make()->title('Relatório não concluído')
                ->body('Não foi possível gerar o PDF. Tente novamente mais tarde.')
                ->danger()->sendToDatabase($user);
        }
    }
}
