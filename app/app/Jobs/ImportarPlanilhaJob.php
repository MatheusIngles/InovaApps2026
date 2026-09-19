<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Support\Import\ImportService;
use App\Support\Import\PlanilhaReader;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/** Importa planilhas grandes fora da requisição e avisa o usuário (notificação do painel) ao terminar. */
class ImportarPlanilhaJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    /** @param  array<string, string|null>  $mapa */
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $caminho,
        public string $extensao,
        public array $mapa,
    ) {}

    public function handle(): void
    {
        $company = Company::findOrFail($this->companyId);
        $user = User::findOrFail($this->userId);

        try {
            $tabela = PlanilhaReader::ler(Storage::path($this->caminho), $this->extensao);
            $r = ImportService::importar($company, $tabela['linhas'], $this->mapa);

            Notification::make()->title('Planilha importada')
                ->body("{$r['clientes']} clientes, {$r['meses']} meses de métricas, {$r['nps']} pesquisas. O risco foi recalculado.")
                ->success()->sendToDatabase($user);
        } catch (\Throwable $e) {
            Notification::make()->title('Importação não concluída')->body($e->getMessage())->danger()->sendToDatabase($user);
        } finally {
            Storage::delete($this->caminho);
        }
    }
}
