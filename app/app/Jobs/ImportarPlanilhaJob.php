<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Support\Import\DynamicImportService;
use App\Support\Import\ImportService;
use App\Support\Import\PlanilhaReader;
use App\Support\Import\TemplateImportService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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
        public bool $strictTemplate = false,
        public ?array $structuralMapping = null,
        public ?array $metricMappings = null,
    ) {}

    public function handle(): void
    {
        $company = Company::findOrFail($this->companyId);
        $user = User::findOrFail($this->userId);

        try {
            if ($user->company_id !== $company->id) {
                throw new InvalidArgumentException('Usuário e planilha pertencem a empresas diferentes.');
            }

            $tabela = $this->structuralMapping !== null || $this->strictTemplate
                ? PlanilhaReader::lerModelo(Storage::path($this->caminho), $this->extensao)
                : PlanilhaReader::ler(Storage::path($this->caminho), $this->extensao);
            $r = $this->structuralMapping !== null
                ? DynamicImportService::importar($company, $tabela, $this->structuralMapping, $this->metricMappings ?? [])
                : ($this->strictTemplate
                    ? TemplateImportService::importar($company, $tabela)
                    : ImportService::importar($company, $tabela['linhas'], $this->mapa));

            Notification::make()->title('Planilha importada')
                ->body("{$r['clientes']} clientes, ".($r['valores_metricas'] ?? 0).' valores de métricas. A atenção foi recalculada.')
                ->success()->sendToDatabase($user);
        } catch (\Throwable $e) {
            Notification::make()->title('Importação não concluída')->body($e->getMessage())->danger()->sendToDatabase($user);
        } finally {
            Storage::delete($this->caminho);
        }
    }
}
