<?php

namespace App\Livewire;

use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/** Visão geral do painel: o que o histórico diz sobre o alerta (corte Alto da empresa). */
#[Lazy]
class PainelResumoEvidencia extends Component
{
    public function placeholder(): string
    {
        return '<div class="ui-card ui-pad ui-muted" role="status">Calculando a evidência do alerta…</div>';
    }

    public function render(): View
    {
        $r = Backtest::resumo(app(CompanyContext::class)->current());

        return view('livewire.painel-resumo-evidencia', ['atual' => $r['limiares']['alto']]);
    }
}
