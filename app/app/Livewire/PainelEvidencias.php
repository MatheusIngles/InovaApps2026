<?php

namespace App\Livewire;

use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use App\Support\Validacao\Configurador;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/** Aba "Evidências" do painel: antecedência do alerta, alarme falso e o que cada variável separa (backtest dos cancelamentos). */
#[Lazy]
class PainelEvidencias extends Component
{
    /** Corte de alerta analisado: medio, alto ou critico (os cortes de nível da empresa). */
    public string $nivel = 'alto';

    public function updatedNivel(): void
    {
        $this->nivel = in_array($this->nivel, ['medio', 'alto', 'critico'], true) ? $this->nivel : 'alto';
    }

    /** Explicação da configuração recomendada, escrita pela IA a partir da evidência da carteira. */
    public ?string $sugestao = null;

    public ?string $fonteSugestao = null;

    public function sugerir(): void
    {
        $r = Configurador::sugerir(app(CompanyContext::class)->current());
        $this->sugestao = $r['texto'];
        $this->fonteSugestao = $r['fonte'];
    }

    public function aplicarConfiguracaoRecomendada(): void
    {
        CompanyConfig::aplicarConfiguracaoDosDados(app(CompanyContext::class)->current());
        $this->redirect('/painel?aba=evidencias'); // recarrega com os novos pesos e cortes
    }

    public function placeholder(): string
    {
        return '<div class="ui-card ui-pad ui-muted" role="status">Calculando o backtest dos cancelamentos…</div>';
    }

    public function render(): View
    {
        $company = app(CompanyContext::class)->current();
        $r = Backtest::resumo($company);

        return view('livewire.painel-evidencias', $r + ['recomendada' => Configurador::recomendada($company), 'atual' => $r['limiares'][$this->nivel], 'linhas' => $r['cancelamentos'][$this->nivel], 'niveis' => $company->limiares()]);
    }
}
