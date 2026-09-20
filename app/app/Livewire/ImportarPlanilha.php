<?php

namespace App\Livewire;

use App\Jobs\ImportarPlanilhaJob;
use App\Models\Customer;
use App\Support\Import\DynamicImportService;
use App\Support\Import\ImportService;
use App\Support\Import\PlanilhaReader;
use App\Support\Tenancy\CompanyContext;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Prévia e confirmação de uma planilha com seis campos estruturais e métricas livres.
 */
class ImportarPlanilha extends Component
{
    use WithFileUploads;

    /** Acima disso (bytes) a importação roda em fila, sem prender a requisição. */
    private const LIMITE_SINCRONO = 2 * 1024 * 1024;

    #[Validate('nullable|file|max:20480|extensions:xlsx,csv')]
    public $arquivo = null;

    /** Travadas: o navegador não pode apontar para outro arquivo do disco (leitura ou exclusão). */
    #[Locked]
    public ?string $caminho = null;

    #[Locked]
    public ?string $extensao = null;

    /** A planilha está no formato padrão (8 sinais): entra pelo importador dos sinais padrão, sem mapeamento. */
    #[Locked]
    public bool $formatoPadrao = false;

    /** @var list<string> */
    public array $cabecalhos = [];

    /** @var list<array<string, mixed>> */
    public array $previa = [];

    /** Dicionário da planilha (campo => tipo, descrição...), usado para preencher as métricas. */
    public array $dicionario = [];

    public array $structuralMapping = [];

    public array $metricMappings = [];

    public ?array $resultado = null;

    public function updatedArquivo(): void
    {
        $this->validateOnly('arquivo');

        if (! $this->arquivo) {
            return;
        }

        $arquivo = $this->arquivo;
        $this->descartar(); // remove o envio anterior
        $extensao = strtolower($arquivo->getClientOriginalExtension());
        $company = app(CompanyContext::class)->current();
        $this->caminho = $arquivo->storeAs('imports/'.$company->id, Str::uuid().'.'.$extensao);
        $this->extensao = $extensao;

        try {
            $tabela = PlanilhaReader::lerModelo(Storage::path($this->caminho), $extensao, previa: 5);
            $this->formatoPadrao = ImportService::ehFormatoPadrao($company, $tabela['cabecalhos']);
            $suggestions = $this->formatoPadrao
                ? ['structure' => [], 'metrics' => []]
                : DynamicImportService::sugerir($company, $tabela['cabecalhos'], $tabela['linhas'], $tabela['dicionario']);
        } catch (\Throwable $e) {
            $this->descartar();
            Notification::make()->title('Não foi possível ler a planilha')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->cabecalhos = $tabela['cabecalhos'];
        $this->previa = array_slice($tabela['linhas'], 0, 5);
        $this->dicionario = $tabela['dicionario'];
        $this->structuralMapping = $suggestions['structure'];
        $this->metricMappings = $suggestions['metrics'];
        $this->resultado = null;
    }

    public function updatedStructuralMapping(): void
    {
        $company = app(CompanyContext::class)->current();
        $used = [...array_filter(array_values($this->structuralMapping)), ...array_values(DynamicImportService::colunasOpcionais($this->cabecalhos))];
        $current = collect($this->metricMappings)->keyBy('column');
        $this->metricMappings = collect($this->cabecalhos)->reject(fn ($header) => in_array($header, $used, true))
            ->map(fn ($header) => $current->get($header) ?? DynamicImportService::sugerirMetrica($company, $header, $this->previa, $this->dicionario))
            ->values()->all();
    }

    /** Troca a coluna de "nova métrica" para uma métrica que a empresa já tem (a primeira, para a pessoa escolher depois). */
    public function usarExistente(int $indice): void
    {
        $primeira = app(CompanyContext::class)->current()->metricDefinitions()->orderBy('code')->value('id');
        if ($primeira && isset($this->metricMappings[$indice])) {
            $this->metricMappings[$indice]['target'] = (string) $primeira;
        }
    }

    public function importar(): void
    {
        $company = app(CompanyContext::class)->current();
        $primeiraCarga = ! Customer::exists();

        if (! $this->caminho || ! Storage::exists($this->caminho)) {
            Notification::make()->title('Selecione uma planilha antes de importar.')->danger()->send();

            return;
        }

        if (Storage::size($this->caminho) > self::LIMITE_SINCRONO) {
            $grande = $this->formatoPadrao
                ? [ImportService::sugerirMapeamento($this->cabecalhos), false, null, null]
                : [[], false, $this->structuralMapping, $this->metricMappings];
            ImportarPlanilhaJob::dispatch($company->id, auth()->id(), $this->caminho, $this->extensao, ...$grande);
            $this->caminho = null; // o job apaga o arquivo ao terminar
            $this->descartar();
            Notification::make()->title('Importação em andamento')->body('A planilha é grande e está sendo processada. Avisamos pelas notificações quando terminar.')->info()->send();

            return;
        }

        try {
            $tabela = PlanilhaReader::lerModelo(Storage::path($this->caminho), $this->extensao);
            $this->resultado = $this->formatoPadrao
                ? ImportService::importar($company, $tabela['linhas'], ImportService::sugerirMapeamento($tabela['cabecalhos'])) + ['valores_metricas' => 0, 'novas_metricas' => 0]
                : DynamicImportService::importar($company, $tabela, $this->structuralMapping, $this->metricMappings);
        } catch (\Throwable $e) {
            Notification::make()->title('Importação não concluída')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->descartar();
        Notification::make()->title('Planilha importada')->body("{$this->resultado['clientes']} clientes, {$this->resultado['valores_metricas']} valores e {$this->resultado['novas_metricas']} métricas novas. A atenção foi recalculada.")->success()->send();

        if ($primeiraCarga) {
            $this->redirect('/'); // dados carregados: segue para a tela da empresa
        }
    }

    public function descartar(): void
    {
        if ($this->caminho) {
            Storage::delete($this->caminho);
        }
        $this->reset('caminho', 'extensao', 'formatoPadrao', 'cabecalhos', 'previa', 'dicionario', 'structuralMapping', 'metricMappings', 'arquivo');
    }

    public function render()
    {
        return view('livewire.importar-planilha');
    }
}
