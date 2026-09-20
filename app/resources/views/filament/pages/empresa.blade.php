@php
    use App\Filament\Pages\Assistente;
    use App\Filament\Resources\Empresas\EmpresaResource;
    use App\Models\Customer;

    $e = $this->record;
    $company = app(\App\Support\Tenancy\CompanyContext::class)->current();
    $rotulo = $e->rotulo();
    $chat = Assistente::getUrl(['empresa' => $e->codigo]);
    $nivelCss = ['Crítico' => 'crit', 'Alto' => 'alto', 'Médio' => 'med', 'Baixo' => 'baixo', 'Resolvido' => 'baixo'][$rotulo] ?? 'canc';
    $hist = $e->hist; // uma consulta só
    $legacy = (bool) $hist;
    $definitions = $company->metricDefinitions()->orderBy('code')->get();
    $customHistory = $e->metricValues()->with('definition')
        ->when($e->cancelled_at, fn ($query) => $query->where('reference_month', '<', $e->cancelled_at))
        ->orderByDesc('reference_month')->get();
    $customMonths = $e->periods()->when($e->cancelled_at, fn ($query) => $query->where('reference_month', '<', $e->cancelled_at))
        ->orderByDesc('reference_month')->pluck('reference_month')->map(fn ($date) => substr($date, 0, 7))
        ->merge($customHistory->map(fn ($value) => $value->reference_month->format('Y-m')))->unique()->sortDesc()->values();
    $customCells = $customHistory->keyBy(fn ($value) => $value->reference_month->format('Y-m').':'.$value->metric_definition_id);
    $ultimo = collect($hist)->last();
    $parcelasPorRotulo = collect($e->contribuicoesScore())->keyBy('rotulo');
    $serie = \App\Filament\Widgets\InsatisfacaoChart::serie($e);
    $prev = $serie['previsao'];
    $ultimoScore = $serie['scores'] ? end($serie['scores']) : null;
@endphp

<x-filament-panels::page>
    <div class="empresa-abas" x-data="{ aba: 'visao' }">
        {{-- Banner, logo e identificação --}}
        <section class="ui-card">
                <div class="ui-banner" role="img" aria-label="Banner do cliente"></div>
                <div class="ui-head">
                    <div class="ui-logo" aria-hidden="true">{{ mb_strtoupper(mb_substr($e->segmento, 0, 1)).ltrim(substr($e->codigo, 1), '0') }}</div>
                    <div class="ui-title">
                        <h1>{{ $e->nome }}</h1>
                        <p class="ui-headline">{{ $e->segmento }}, porte {{ $e->porte }}, plano {{ $e->plano }}. Cliente desde {{ date('m/Y', strtotime($e->inicio)) }}{{ $e->cancelada() ? ', cancelou em '.$e->mes_cancel : '' }}.</p>
                    </div>
                    <div class="ui-actions">
                        <span class="ui-badge {{ $nivelCss }}">{{ $e->currentAssessment ? $rotulo.' · atenção '.$e->score.'/100' : $rotulo }}{{ $e->cancelada() ? ' antes da saída' : '' }}</span>
                        @unless ($e->cancelada())
                            <button type="button" class="ui-btn" wire:click="alternarResolvido">{{ $e->resolvida() ? 'Reabrir' : 'Marcar como resolvido' }}</button>
                        @endunless
                        <a class="ui-btn primary" href="{{ $chat }}">Conversar com a IA</a>
                        <livewire:relatorio-empresa :codigo="$e->codigo" :key="'relatorio-'.$e->codigo" />
                    </div>
                </div>
        </section>

        <nav class="empresa-abas-nav fi-tabs" role="tablist" aria-label="Informações do cliente">
            <button type="button" class="fi-tabs-item" role="tab" id="empresa-tab-visao" x-ref="visao" aria-controls="empresa-painel-visao" :aria-selected="aba === 'visao'" :tabindex="aba === 'visao' ? 0 : -1" :class="{ 'fi-active': aba === 'visao' }" @click="aba = 'visao'" @keydown.arrow-right.prevent="aba = 'historico'; $refs.historico.focus()">Visão geral</button>
            <button type="button" class="fi-tabs-item" role="tab" id="empresa-tab-historico" x-ref="historico" aria-controls="empresa-painel-historico" :aria-selected="aba === 'historico'" :tabindex="aba === 'historico' ? 0 : -1" :class="{ 'fi-active': aba === 'historico' }" @click="aba = 'historico'" @keydown.arrow-right.prevent="aba = 'tendencia'; $refs.tendencia.focus()" @keydown.arrow-left.prevent="aba = 'visao'; $refs.visao.focus()">Histórico mensal</button>
            <button type="button" class="fi-tabs-item" role="tab" id="empresa-tab-tendencia" x-ref="tendencia" aria-controls="empresa-painel-tendencia" :aria-selected="aba === 'tendencia'" :tabindex="aba === 'tendencia' ? 0 : -1" :class="{ 'fi-active': aba === 'tendencia' }" @click="aba = 'tendencia'" @keydown.arrow-left.prevent="aba = 'historico'; $refs.historico.focus()">Tendência e previsão</button>
        </nav>

        <div id="empresa-painel-visao" role="tabpanel" aria-labelledby="empresa-tab-visao" x-show="aba === 'visao'" class="perfil">
          <div class="ui-main">
            {{-- Indicadores atuais --}}
            <section class="ui-card ui-pad">
                <h2>Indicadores atuais</h2>
                <dl class="ui-stats">
                    <div><dt>Contrato/mês</dt><dd>{{ Customer::brl($e->valor) }}</dd></div>
                    @if ($legacy)
                    <div><dt>Uso da plataforma</dt><dd>{{ $ultimo ? $ultimo['uso'].'%' : '—' }}</dd></div>
                    <div><dt>SLA cumprido</dt><dd>{{ $ultimo && is_numeric($ultimo['sla']) ? $ultimo['sla'].'%' : '—' }}</dd></div>
                    @else
                    <div><dt>Métricas configuradas</dt><dd>{{ $definitions->count() }}</dd></div>
                    <div><dt>Métricas observadas</dt><dd>{{ $customHistory->pluck('metric_definition_id')->unique()->count() }}</dd></div>
                    <div><dt>Meses importados</dt><dd>{{ $customMonths->count() }}</dd></div>
                    @endif
                    <div><dt>Atenção <details class="ui-tip"><summary aria-label="Como a atenção é calculada">?</summary><span class="ui-tip-content">Soma ponderada dos sinais avaliados, incluindo métricas próprias quando cadastradas. É um índice de 0 a 100, não a chance de cancelamento.</span></details></dt><dd>{{ $e->currentAssessment ? $e->score.'/100' : '—' }}</dd></div>
                    <div><dt>Exposição <details class="ui-tip"><summary aria-label="Como a exposição é calculada">?</summary><span class="ui-tip-content">{{ $e->score }}/100 × {{ Customer::brl($e->valor) }}/mês. É um indicador para priorização, não uma perda prevista.</span></details></dt><dd>{{ Customer::brl($e->exposicao) }}</dd></div>
                </dl>
            </section>

            {{-- Previsão da atenção --}}
            @if ($serie['scores'])
            <section class="ui-card ui-pad">
                <h2>Previsão da atenção</h2>
                    <p>
                        @if ($prev)
                            A reta dos últimos {{ $prev['pontos'] }} meses ({{ $prev['tendencia'] > 0 ? '+' : '' }}{{ number_format($prev['tendencia'], 1, ',', '.') }} pts por mês) aponta para <b>{{ $prev['valor'] }}</b> no próximo mês (faixa provável de {{ $prev['minimo'] }} a {{ $prev['maximo'] }}). O último mês fechou em {{ $ultimoScore }}.
                            @if ($prev['tendencia'] >= 1) A atenção necessária está <b>subindo</b>: vale agir antes do próximo mês. @elseif ($prev['tendencia'] <= -1) A atenção necessária está <b>caindo</b>. @else A direção é <b>estável</b>. @endif
                            @if (abs($ultimoScore - $prev['ajuste_ultimo']) > max(3, $prev['maximo'] - $prev['valor'])) O último mês ficou fora da tendência; a previsão suaviza esse desvio. @endif
                            <details class="ui-tip"><summary aria-label="Como a previsão foi calculada">?</summary><span class="ui-tip-content">Pegamos a atenção (índice de sinais) de cada um dos últimos {{ $prev['pontos'] }} meses e traçamos a reta que melhor se ajusta a eles (mínimos quadrados). A inclinação da reta é a tendência, em pontos por mês; o ponto seguinte da reta é a previsão. A faixa provável é a previsão mais ou menos o desvio típico dos meses em torno da reta, sempre entre 0 e 100. "Subindo" = tendência de +1 ponto por mês ou mais; "caindo" = -1 ou menos; no meio, estável. Se o último mês fica longe da reta (mais que a faixa), avisamos que fugiu da tendência. Não é probabilidade de cancelamento: só indica a direção.</span></details>
                        @elseif ($e->cancelada())
                            Cliente cancelado: o gráfico mostra os meses até a saída.
                        @else
                            Poucos meses de histórico para projetar uma tendência.
                        @endif
                    </p>
            </section>
            @endif

            {{-- Destaques: sinais e próximos passos --}}
            <section class="ui-card ui-pad">
                <h2>{{ $e->cancelada() ? 'Sinais antes do cancelamento' : 'Em destaque: sinais de alerta e próximos passos' }}</h2>
                @unless ($e->currentAssessment)
                    <p class="ui-muted">Sem avaliação calculada: faltam métricas mensais para este cliente.</p>
                @endunless
                @forelse ($e->sinais as $s)
                    @php($parcela = $parcelasPorRotulo->get($s['label']))
                    <article class="ui-sinal">
                        <div class="ui-sinal-top">
                            <strong>{{ $s['label'] }}</strong>
                            @if ($parcela)
                                <details class="ui-tip ui-tip-valor">
                                    <summary aria-label="Como {{ $s['label'] }} contribuiu para a atenção">+{{ $s['pts'] }} pts</summary>
                                    <span class="ui-tip-content">Parcela da métrica: {{ number_format($parcela['base'], 1, ',', '.') }} pt (intensidade × {{ number_format($parcela['equal_share'] ?? 12.5, 1, ',', '.') }}). Ajuste da prioridade: {{ $parcela['ajuste_prioridade'] < 0 ? '−' : '+' }}{{ number_format(abs($parcela['ajuste_prioridade']), 1, ',', '.') }} pt (peso {{ number_format($parcela['peso'], 1, ',', '.') }}). Total: {{ number_format($parcela['pontos'], 1, ',', '.') }} pt.</span>
                                </details>
                            @else
                                <span class="ui-sinal-pontos">+{{ $s['pts'] }} pts</span>
                            @endif
                        </div>
                        <p>{{ $s['texto'] }}</p>
                        @unless ($e->cancelada())<p class="ui-acao">{{ $s['acao'] }}</p>@endunless
                    </article>
                @empty
                    <p class="ui-muted">Nenhum sinal relevante nos últimos 3 meses.</p>
                @endforelse
            </section>

        </div>

        <aside class="ui-rail">
            <section class="ui-card ui-pad">
                <h2>Clientes parecidos que cancelaram</h2>
                <p class="ui-muted">Perfil dos últimos 3 meses comparado ao de quem já saiu. 100% indica sinais iguais, não chance de cancelamento.</p>
                <ul class="ui-list">
                    @foreach ($e->similares as $s)
                        <li>
                            <a href="{{ EmpresaResource::getUrl('view', ['record' => $s['codigo']]) }}">
                                <span class="ui-mini" aria-hidden="true">{{ mb_strtoupper(mb_substr($s['nome'], 0, 1)).ltrim(substr($s['codigo'], 1), '0') }}</span>
                                <span><strong>{{ $s['nome'] }}</strong><small>Cancelou em {{ $s['mes_cancel'] }} · {{ $s['sim'] }}% de semelhança</small></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($legacy)
            <section class="ui-card ui-pad">
                <h2>Satisfação (NPS)</h2>
                <div class="ui-nps">
                    @foreach ($e->nps as $n)
                        <div><small>{{ $n['mes'] }}</small><b class="{{ $n['nota'] === '—' ? 'sem' : ((int) $n['nota'] >= 9 ? 'pro' : ((int) $n['nota'] >= 7 ? 'neu' : 'det')) }}">{{ $n['nota'] }}</b></div>
                    @endforeach
                </div>
                <p class="ui-muted">— = convidado e não respondeu.</p>
            </section>
            @endif
        </aside>
        </div>

        <section id="empresa-painel-historico" role="tabpanel" aria-labelledby="empresa-tab-historico" x-show="aba === 'historico'" x-cloak class="ui-card ui-pad">
            <h2>Histórico mensal</h2>
            @if ($hist)
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Mês</th><th>Chamados</th><th>Reabertos</th><th>SLA %</th><th>Uso %</th><th>Reclam.</th><th>Atraso (d)</th><th>Reuniões</th></tr></thead>
                    <tbody>
                        @foreach (array_reverse($hist) as $h)
                            <tr><td>{{ $h['mes'] }}</td><td>{{ $h['abertos'] }}</td><td>{{ $h['reabertos'] }}</td><td>{{ $h['sla'] }}</td><td>{{ $h['uso'] }}</td><td>{{ $h['recl'] }}</td><td>{{ $h['atraso'] }}</td><td>{{ $h['reunioes'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            @if ($customMonths->isNotEmpty() && $definitions->isNotEmpty())
                <h3>Métricas</h3>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Mês</th><th>Valor mensal</th>@foreach ($definitions as $definition)<th title="{{ $definition->description }}">{{ $definition->label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($customMonths as $month)
                                @php($period = $e->periods->first(fn ($entry) => $entry->reference_month->format('Y-m') === $month))
                                <tr><td>{{ $month }}</td><td>{{ $period?->monthly_value !== null ? Customer::brl((float) $period->monthly_value) : '—' }}</td>@foreach ($definitions as $definition)
                                    @php($observation = $customCells->get($month.':'.$definition->id))
                                    <td>{{ $observation ? (\App\Models\MetricDefinition::semScore($definition->value_type) ? $observation->text_value : number_format((float) $observation->value, $definition->value_type === 'integer' ? 0 : 2, ',', '.')) : '—' }}</td>
                                @endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section id="empresa-painel-tendencia" role="tabpanel" aria-labelledby="empresa-tab-tendencia" x-show="aba === 'tendencia'" x-cloak class="ui-main">
            <?php
                $resumo = $e->cancelada() ? null : \App\Support\Validacao\Backtest::resumo(app(\App\Support\Tenancy\CompanyContext::class)->current());
                $comparacao = ($resumo['variaveis'] ?? []) + array_filter($resumo['metricas_proprias'] ?? [], fn ($v) => $v['auc'] !== null);
                $signals = $e->currentAssessment?->signals_json ?? [];
                $sev = $signals['severity'] ?? [];
                $sev = (count($sev) === count(\App\Support\Risco::ROTULOS) ? array_combine(array_keys(\App\Support\Risco::ROTULOS), $sev) : []) + ($signals['custom_severity'] ?? []);
            ?>
            <section class="ui-card ui-pad">
                <h2>Atenção ao longo dos meses</h2>
                @if ($serie['scores'])
                    @livewire(\App\Filament\Widgets\InsatisfacaoChart::class, ['codigo' => $e->codigo], key('insatisfacao-'.$e->codigo))
                    <p class="ui-muted">A previsão acompanha a tendência recente do índice de atenção calculado com as métricas disponíveis. Não é probabilidade de cancelamento.</p>
                @else
                    <p class="ui-muted">Faltam meses de histórico para montar a série.</p>
                @endif
            </section>

            @if ($comparacao && $sev)
                <section class="ui-card ui-pad">
                    <h2>Este cliente comparado a quem cancelou e a quem ficou</h2>
                    <p class="ui-muted">Gravidade de cada sinal hoje (0 a 100%). Quanto mais perto do valor dos cancelados, mais o padrão se parece com o de quem saiu.</p>
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead><tr><th>Sinal</th><th>Este cliente</th><th>Média dos que ficaram</th><th>Média dos que cancelaram</th></tr></thead>
                            <tbody>
                                @foreach (collect($comparacao)->filter(fn ($v, $k) => array_key_exists($k, $sev))->sortByDesc('auc') as $k => $v)
                                    @php($minha = $sev[$k] ?? 0)
                                    <tr @if ($minha >= $v['media_cancelados'] * 0.8 && $v['media_cancelados'] >= 0.3) class="ev-atual" @endif>
                                        <td>{{ $v['rotulo'] }}</td>
                                        <td><b>{{ round($minha * 100) }}%</b></td>
                                        <td>{{ round($v['media_retidos'] * 100) }}%</td>
                                        <td>{{ round($v['media_cancelados'] * 100) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </section>
    </div>

    <a class="ui-fab" href="{{ $chat }}" aria-label="Abrir o chat com a IA de {{ $e->nome }}">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12c0 4-4 8-9 8a9.9 9.9 0 0 1-4-.8L3 20l1.3-3.9A7.6 7.6 0 0 1 3 12c0-4 4-8 9-8s9 4 9 8z"/></svg>
        Chat com a IA
    </a>
</x-filament-panels::page>
