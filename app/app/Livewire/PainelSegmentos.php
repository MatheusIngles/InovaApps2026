<?php

namespace App\Livewire;

use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use App\Support\Validacao\Configurador;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/** Aba "Por segmento": o que estava elevado nos cancelados de cada segmento e quem ainda ativo repete o padrão. */
#[Lazy]
class PainelSegmentos extends Component
{
    public ?string $segmento = null;

    public function placeholder(): string
    {
        return '<div class="ui-card ui-pad ui-muted" role="status">Analisando os segmentos…</div>';
    }

    public function aplicarConfiguracaoRecomendada(): void
    {
        CompanyConfig::aplicarConfiguracaoDosDados(app(CompanyContext::class)->current());
        $this->redirect('/painel?aba=por-segmento'); // recarrega com os novos pesos e cortes
    }

    public function render(): View
    {
        $company = app(CompanyContext::class)->current();
        $r = Backtest::resumo($company);
        $segmentos = $r['segmentos'];
        $atual = $segmentos[$this->segmento] ?? reset($segmentos) ?: null; // padrão: o segmento com maior taxa de cancelamento

        return view('livewire.painel-segmentos', ['segmentos' => $segmentos, 'atual' => $atual, 'extras' => $r['extras'], 'perfis' => $r['perfis'], 'evidencia_suficiente' => $r['evidencia_suficiente'], 'recomendada' => Configurador::recomendada($company)]);
    }
}
