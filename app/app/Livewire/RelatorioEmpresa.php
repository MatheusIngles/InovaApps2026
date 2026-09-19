<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Support\Relatorio\RelatorioService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Throwable;

/** Modal na página da empresa: escolher quais pontos críticos entram no relatório em PDF (análise por IA). */
class RelatorioEmpresa extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public string $codigo;

    public ?array $data = ['sinais' => [], 'observacoes' => null];

    public function mount(string $codigo): void
    {
        $this->codigo = $codigo;
    }

    private function empresa(): Customer
    {
        return Customer::dashboard()->where('customers.external_code', $this->codigo)->firstOrFail();
    }

    public function abrir(): void
    {
        $this->form->fill(['sinais' => array_keys($this->empresa()->sinais), 'observacoes' => null]);
        $this->dispatch('open-modal', id: 'relatorio-empresa');
    }

    public function form(Schema $schema): Schema
    {
        $sinais = $this->empresa()->sinais;

        return $schema->statePath('data')->components([
            CheckboxList::make('sinais')
                ->hiddenLabel()
                ->options(collect($sinais)->mapWithKeys(fn ($s, $i) => [$i => "{$s['label']} (+{$s['pts']} pts)"]))
                ->descriptions(collect($sinais)->mapWithKeys(fn ($s, $i) => [$i => $s['texto']]))
                ->columns(1)
                ->required(),
            Textarea::make('observacoes')
                ->label('O que você quer que o relatório destaque?')
                ->placeholder('Opcional — ex.: foco em risco de cancelamento, ou em oportunidades de upsell.')
                ->rows(3)
                ->maxLength(500),
        ]);
    }

    public function gerar(): mixed
    {
        $dados = $this->form->getState();
        $indices = array_map('intval', $dados['sinais'] ?? []);

        $empresa = $this->empresa();

        try {
            $pdf = RelatorioService::gerar($empresa, $indices, $dados['observacoes'] ?? null);
        } catch (Throwable $e) {
            Notification::make()->title('Não foi possível gerar o relatório')->body($e->getMessage())->danger()->send();

            return null;
        }

        $this->dispatch('close-modal', id: 'relatorio-empresa');

        return response()->streamDownload(fn () => print ($pdf), "relatorio-{$empresa->codigo}.pdf");
    }

    public function render()
    {
        return view('livewire.relatorio-empresa');
    }
}
