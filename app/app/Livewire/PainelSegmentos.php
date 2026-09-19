<?php

namespace App\Livewire;

use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
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

    public function render(): View
    {
        $segmentos = Backtest::resumo(app(CompanyContext::class)->current())['segmentos'];
        $atual = $segmentos[$this->segmento] ?? reset($segmentos) ?: null; // padrão: o segmento com maior taxa de cancelamento

        return view('livewire.painel-segmentos', ['segmentos' => $segmentos, 'atual' => $atual]);
    }
}
