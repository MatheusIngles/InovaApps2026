<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Support\Import\ImportService;
use App\Support\Import\PlanilhaReader;
use App\Support\Tenancy\CompanyContext;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Envio de planilha (XLSX/CSV) com mapeamento de colunas. Usado na população inicial (tela Planilha)
 * e para acrescentar meses novos (tela Configurações).
 */
class ImportarPlanilha extends Component implements HasSchemas
{
    use InteractsWithSchemas, WithFileUploads;

    #[Validate('nullable|file|max:20480|extensions:xlsx,csv,txt')]
    public $arquivo = null;

    public ?string $caminho = null;

    public ?string $extensao = null;

    /** @var list<string> */
    public array $cabecalhos = [];

    /** @var list<array<string, mixed>> */
    public array $previa = [];

    public ?array $data = ['mapa' => []];

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
            $tabela = PlanilhaReader::ler(Storage::path($this->caminho), $extensao);
        } catch (\Throwable $e) {
            $this->descartar();
            Notification::make()->title('Não foi possível ler a planilha')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->cabecalhos = $tabela['cabecalhos'];
        $this->previa = array_slice($tabela['linhas'], 0, 5);
        $this->data['mapa'] = ImportService::sugerirMapeamento($this->cabecalhos, $company->column_mapping ?? []);
        $this->resultado = null;
    }

    public function importar(): void
    {
        $mapa = $this->form->getState()['mapa'] ?? [];
        $company = app(CompanyContext::class)->current();
        $primeiraCarga = ! Customer::exists();

        try {
            $tabela = PlanilhaReader::ler(Storage::path($this->caminho), $this->extensao);
            $this->resultado = ImportService::importar($company, $tabela['linhas'], $mapa);
        } catch (\Throwable $e) {
            Notification::make()->title('Importação não concluída')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->descartar();
        Notification::make()->title('Planilha importada')->body("{$this->resultado['clientes']} clientes, {$this->resultado['meses']} meses de métricas, {$this->resultado['nps']} pesquisas. O risco foi recalculado.")->success()->send();

        if ($primeiraCarga) {
            $this->redirect('/'); // dados carregados: segue para a tela da empresa
        }
    }

    public function descartar(): void
    {
        if ($this->caminho) {
            Storage::delete($this->caminho);
        }
        $this->reset('caminho', 'extensao', 'cabecalhos', 'previa', 'arquivo');
        $this->data['mapa'] = [];
    }

    public function form(Schema $schema): Schema
    {
        $opcoes = array_combine($this->cabecalhos, $this->cabecalhos);

        return $schema->statePath('data')->components([
            Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema(
                collect(ImportService::CAMPOS)->map(fn ($def, $campo) => Select::make("mapa.$campo")
                    ->label($def[0])
                    ->options($opcoes)
                    ->placeholder('— não usar —')
                    ->searchable()
                    ->required($campo === 'cliente_id')
                )->values()->all()
            ),
        ]);
    }

    public function render()
    {
        return view('livewire.importar-planilha');
    }
}
