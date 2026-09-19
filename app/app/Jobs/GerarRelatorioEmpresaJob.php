<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Customer;
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

/** Gera o PDF fora da requisição e avisa apenas o usuário que solicitou. */
class GerarRelatorioEmpresaJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    /** @param list<int> $indices */
    public function __construct(
        public int $companyId,
        public int $userId,
        public int $customerId,
        public string $arquivoId,
        public array $indices,
        public ?string $observacoes,
    ) {}

    public static function caminho(int $companyId, int $userId, string $arquivoId): string
    {
        return "relatorios/{$companyId}/{$userId}/{$arquivoId}.pdf";
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || $user->company_id !== $this->companyId) {
            return;
        }

        try {
            $company = Company::findOrFail($this->companyId);
            $empresa = app(CompanyContext::class)->within($company, function (): Customer {
                return Customer::dashboard()->whereKey($this->customerId)->firstOrFail();
            });
            $pdf = app(CompanyContext::class)->within($company, fn (): string => RelatorioService::gerar($empresa, $this->indices, $this->observacoes));
            $caminho = self::caminho($this->companyId, $this->userId, $this->arquivoId);

            if (! Storage::disk('local')->put($caminho, $pdf)) {
                throw new \RuntimeException('Não foi possível salvar o PDF.');
            }

            Notification::make()->title('Relatório pronto')
                ->body("O relatório de {$empresa->nome} está disponível para download.")
                ->success()->actions([
                    Action::make('baixar')->label('Baixar PDF')
                        ->url(route('relatorios.download', ['arquivo' => $this->arquivoId]))->markAsRead(),
                ])->sendToDatabase($user);
        } catch (Throwable $e) {
            Log::error('Falha ao gerar relatório da empresa.', [
                'company_id' => $this->companyId,
                'customer_id' => $this->customerId,
                'exception' => $e,
            ]);
            Notification::make()->title('Relatório não concluído')
                ->body('Não foi possível gerar o PDF. Tente novamente mais tarde.')
                ->danger()->sendToDatabase($user);
        }
    }
}
