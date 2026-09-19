@php
    use App\Filament\Pages\Assistente;
    use App\Filament\Resources\Empresas\EmpresaResource;
    use App\Models\Customer;

    $e = $this->record;
    $rotulo = $e->rotulo();
    $chat = Assistente::getUrl(['empresa' => $e->codigo]);
    $nivelCss = ['Crítico' => 'crit', 'Alto' => 'alto', 'Médio' => 'med', 'Baixo' => 'baixo'][$rotulo] ?? 'canc';
    $hist = $e->hist; // uma consulta só
    $ultimo = collect($hist)->last();
    $parcelasPorRotulo = collect($e->contribuicoesScore())->keyBy('rotulo');
@endphp

<x-filament-panels::page>
    <div class="empresa-abas" x-data="{ aba: 'visao' }">
        {{-- Banner, logo e identificação --}}
        <section class="ui-card">
                <div class="ui-banner" role="img" aria-label="Banner da empresa"></div>
                <div class="ui-head">
                    <div class="ui-logo" aria-hidden="true">{{ mb_strtoupper(mb_substr($e->segmento, 0, 1)).ltrim(substr($e->codigo, 1), '0') }}</div>
                    <div class="ui-title">
                        <h1>{{ $e->nome }}</h1>
                        <p class="ui-headline">{{ $e->segmento }}, porte {{ $e->porte }}, plano {{ $e->plano }}. Cliente desde {{ date('m/Y', strtotime($e->inicio)) }}{{ $e->cancelada() ? ', cancelou em '.$e->mes_cancel : '' }}.</p>
                    </div>
                    <div class="ui-actions">
                        <span class="ui-badge {{ $nivelCss }}">{{ $rotulo }} · {{ $e->score }}/100{{ $e->cancelada() ? ' antes da saída' : '' }}</span>
                        <a class="ui-btn primary" href="{{ $chat }}">Conversar com a IA</a>
                        <livewire:relatorio-empresa :codigo="$e->codigo" :key="'relatorio-'.$e->codigo" />
                        <a class="ui-btn" href="{{ EmpresaResource::getUrl() }}">Todas as empresas</a>
                    </div>
                </div>
        </section>

        <nav class="empresa-abas-nav fi-tabs" role="tablist" aria-label="Informações da empresa">
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
                    <div><dt>Uso da plataforma</dt><dd>{{ $ultimo ? $ultimo['uso'].'%' : '—' }}</dd></div>
                    <div><dt>SLA cumprido</dt><dd>{{ $ultimo && is_numeric($ultimo['sla']) ? $ultimo['sla'].'%' : '—' }}</dd></div>
                    <div><dt>Score de sinais <details class="ui-tip"><summary aria-label="Como o score é calculado">?</summary><span class="ui-tip-content">Soma das parcelas dos oito sinais avaliados. É uma pontuação de risco, não uma chance de cancelamento.</span></details></dt><dd>{{ $e->score }}/100</dd></div>
                    <div><dt>Exposição <details class="ui-tip"><summary aria-label="Como a exposição é calculada">?</summary><span class="ui-tip-content">{{ $e->score }} ÷ 100 × {{ Customer::brl($e->valor) }}/mês. É um indicador para priorização, não uma perda prevista.</span></details></dt><dd>{{ Customer::brl($e->exposicao) }}</dd></div>
                </dl>
            </section>

            {{-- Destaques: sinais e próximos passos --}}
            <section class="ui-card ui-pad">
                <h2>{{ $e->cancelada() ? 'Sinais antes do cancelamento' : 'Em destaque: sinais de alerta e próximos passos' }}</h2>
                @unless ($e->currentAssessment)
                    <p class="ui-muted">Sem avaliação calculada: faltam métricas mensais para esta empresa.</p>
                @endunless
                @forelse ($e->sinais as $s)
                    @php($parcela = $parcelasPorRotulo->get($s['label']))
                    <article class="ui-sinal">
                        <div class="ui-sinal-top">
                            <strong>{{ $s['label'] }}</strong>
                            @if ($parcela)
                                <details class="ui-tip ui-tip-valor">
                                    <summary aria-label="Como {{ $s['label'] }} contribuiu para o score">+{{ $s['pts'] }} pts</summary>
                                    <span class="ui-tip-content">Parcela da métrica: {{ number_format($parcela['base'], 1, ',', '.') }} pt (intensidade × 12,5). Ajuste da prioridade: {{ $parcela['ajuste_prioridade'] < 0 ? '−' : '+' }}{{ number_format(abs($parcela['ajuste_prioridade']), 1, ',', '.') }} pt (peso {{ number_format($parcela['peso'], 1, ',', '.') }}). Total: {{ number_format($parcela['pontos'], 1, ',', '.') }} pt.</span>
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
                <h2>Empresas parecidas que cancelaram</h2>
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

            <section class="ui-card ui-pad">
                <h2>Satisfação (NPS)</h2>
                <div class="ui-nps">
                    @foreach ($e->nps as $n)
                        <div><small>{{ $n['mes'] }}</small><b class="{{ $n['nota'] === '—' ? 'sem' : ((int) $n['nota'] >= 9 ? 'pro' : ((int) $n['nota'] >= 7 ? 'neu' : 'det')) }}">{{ $n['nota'] }}</b></div>
                    @endforeach
                </div>
                <p class="ui-muted">— = convidado e não respondeu.</p>
            </section>
        </aside>
        </div>

        <section id="empresa-painel-historico" role="tabpanel" aria-labelledby="empresa-tab-historico" x-show="aba === 'historico'" x-cloak class="ui-card ui-pad">
            <h2>Histórico mensal</h2>
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
        </section>

        <section id="empresa-painel-tendencia" role="tabpanel" aria-labelledby="empresa-tab-tendencia" x-show="aba === 'tendencia'" x-cloak class="ui-main">
            <?php
                $serie = \App\Filament\Widgets\InsatisfacaoChart::serie($e);
                $prev = $serie['previsao'];
                $ultimoScore = $serie['scores'] ? end($serie['scores']) : null;
                $comparacao = $e->cancelada() ? [] : \App\Support\Validacao\Backtest::resumo(app(\App\Support\Tenancy\CompanyContext::class)->current())['variaveis'];
                $sev = $e->currentAssessment?->signals_json['severity'] ?? [];
                $sev = count($sev) === count(\App\Support\Risco::ROTULOS) ? array_combine(array_keys(\App\Support\Risco::ROTULOS), $sev) : [];
            ?>
            <section class="ui-card ui-pad">
                <h2>Insatisfação ao longo dos meses</h2>
                @if ($serie['scores'])
                    <p>
                        @if ($prev)
                            A reta dos últimos {{ $prev['pontos'] }} meses ({{ $prev['tendencia'] > 0 ? '+' : '' }}{{ number_format($prev['tendencia'], 1, ',', '.') }} pts por mês) aponta para <b>{{ $prev['valor'] }}</b> no próximo mês (faixa provável de {{ $prev['minimo'] }} a {{ $prev['maximo'] }}). O último mês fechou em {{ $ultimoScore }}.
                            @if ($prev['tendencia'] >= 1) O risco está <b>subindo</b>: vale agir antes do próximo mês. @elseif ($prev['tendencia'] <= -1) O risco está <b>caindo</b>. @else A direção é <b>estável</b>. @endif
                            @if (abs($ultimoScore - $prev['ajuste_ultimo']) > max(3, $prev['maximo'] - $prev['valor'])) O último mês ficou fora da tendência; a previsão suaviza esse desvio. @endif
                            <details class="ui-tip"><summary aria-label="Como a previsão foi calculada">?</summary><span class="ui-tip-content">Pegamos o score de sinais de cada um dos últimos {{ $prev['pontos'] }} meses e traçamos a reta que melhor se ajusta a eles (mínimos quadrados). A inclinação da reta é a tendência, em pontos por mês; o ponto seguinte da reta é a previsão. A faixa provável é a previsão mais ou menos o desvio típico dos meses em torno da reta, sempre entre 0 e 100. "Subindo" = tendência de +1 ponto por mês ou mais; "caindo" = -1 ou menos; no meio, estável. Se o último mês fica longe da reta (mais que a faixa), avisamos que fugiu da tendência. Não é probabilidade de cancelamento: só indica a direção.</span></details>
                        @elseif ($e->cancelada())
                            Cliente cancelado: o gráfico mostra os meses até a saída.
                        @else
                            Poucos meses de histórico para projetar uma tendência.
                        @endif
                    </p>
                    @livewire(\App\Filament\Widgets\InsatisfacaoChart::class, ['codigo' => $e->codigo], key('insatisfacao-'.$e->codigo))
                    <p class="ui-muted">A previsão é a reta da tendência recente do score de sinais (uso, SLA, reclamações, NPS e outros). Não é probabilidade de cancelamento; serve para antecipar a direção.</p>
                @else
                    <p class="ui-muted">Faltam meses de histórico para montar a série.</p>
                @endif
            </section>

            @if ($comparacao && $sev)
                <section class="ui-card ui-pad">
                    <h2>Esta empresa comparada a quem cancelou e a quem ficou</h2>
                    <p class="ui-muted">Gravidade de cada sinal hoje (0 a 100%). Quanto mais perto do valor dos cancelados, mais o padrão se parece com o de quem saiu.</p>
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead><tr><th>Sinal</th><th>Esta empresa</th><th>Média dos que ficaram</th><th>Média dos que cancelaram</th></tr></thead>
                            <tbody>
                                @foreach (collect($comparacao)->sortByDesc('auc') as $k => $v)
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
