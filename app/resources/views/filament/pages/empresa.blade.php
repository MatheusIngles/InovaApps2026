@php
    use App\Filament\Pages\Assistente;
    use App\Filament\Resources\Empresas\EmpresaResource;
    use App\Models\Customer;

    $e = $this->record;
    $rotulo = $e->rotulo();
    $chat = Assistente::getUrl(['empresa' => $e->codigo]);
    $nivelCss = ['Crítico' => 'crit', 'Alto' => 'alto', 'Médio' => 'med', 'Baixo' => 'baixo'][$rotulo] ?? 'canc';
    $ultimo = collect($e->hist)->last();
@endphp

<x-filament-panels::page>
    <div class="li">
        <div class="li-main">
            {{-- Banner, logo e identificação --}}
            <section class="li-card">
                <div class="li-banner" role="img" aria-label="Banner da empresa"></div>
                <div class="li-head">
                    <div class="li-logo" aria-hidden="true">{{ mb_strtoupper(mb_substr($e->segmento, 0, 1)).ltrim(substr($e->codigo, 1), '0') }}</div>
                    <div class="li-title">
                        <h1>{{ $e->nome }}</h1>
                        <p class="li-headline">{{ $e->segmento }} · porte {{ $e->porte }} · plano {{ $e->plano }}</p>
                        <p class="li-meta">Cliente desde {{ date('m/Y', strtotime($e->inicio)) }}{{ $e->cancelada() ? ' · cancelou em '.$e->mes_cancel : '' }}</p>
                    </div>
                    <div class="li-actions">
                        <span class="li-badge {{ $nivelCss }}">{{ $rotulo }}{{ $e->cancelada() ? '' : ' · '.$e->score.'/100' }}</span>
                        <a class="li-btn primary" href="{{ $chat }}">Conversar com a IA</a>
                        <a class="li-btn" href="{{ EmpresaResource::getUrl() }}">Todas as empresas</a>
                    </div>
                </div>
            </section>

            {{-- Sobre --}}
            <section class="li-card li-pad">
                <h2>Sobre</h2>
                <p class="li-about">
                    Empresa do setor de {{ mb_strtolower($e->segmento) }}, de porte {{ mb_strtolower($e->porte) }}, cliente desde {{ date('m/Y', strtotime($e->inicio)) }}
                    no plano {{ $e->plano }}, com contrato de {{ Customer::brl($e->valor) }} por mês e SLA de {{ $e->sla_h }}h.
                    @if ($e->cancelada())
                        O contrato foi encerrado em {{ $e->mes_cancel }}.
                    @else
                        O score de sinais atual é {{ $e->score }}/100 ({{ $e->nivel }}), com exposição mensal indicativa de {{ Customer::brl($e->exposicao) }}.
                    @endif
                </p>
                <dl class="li-stats">
                    <div><dt>Contrato/mês</dt><dd>{{ Customer::brl($e->valor) }}</dd></div>
                    <div><dt>Uso da plataforma</dt><dd>{{ $ultimo ? $ultimo['uso'].'%' : '—' }}</dd></div>
                    <div><dt>SLA cumprido</dt><dd>{{ $ultimo && is_numeric($ultimo['sla']) ? $ultimo['sla'].'%' : '—' }}</dd></div>
                    <div><dt>Exposição</dt><dd>{{ Customer::brl($e->exposicao) }}</dd></div>
                </dl>
            </section>

            {{-- Destaques: sinais e próximos passos --}}
            <section class="li-card li-pad">
                <h2>{{ $e->cancelada() ? 'Sinais antes do cancelamento' : 'Em destaque: sinais de alerta e próximos passos' }}</h2>
                @forelse ($e->sinais as $s)
                    <article class="li-sinal">
                        <div class="li-sinal-top"><strong>{{ $s['label'] }}</strong><span>+{{ $s['pts'] }} pts</span></div>
                        <p>{{ $s['texto'] }}</p>
                        @unless ($e->cancelada())<p class="li-acao">→ {{ $s['acao'] }}</p>@endunless
                    </article>
                @empty
                    <p class="li-muted">Nenhum sinal relevante nos últimos 3 meses.</p>
                @endforelse
            </section>

            {{-- Histórico --}}
            <section class="li-card li-pad">
                <h2>Histórico mensal</h2>
                <div class="li-table-wrap">
                    <table class="li-table">
                        <thead><tr><th>Mês</th><th>Chamados</th><th>Reabertos</th><th>SLA %</th><th>Uso %</th><th>Reclam.</th><th>Atraso (d)</th><th>Reuniões</th></tr></thead>
                        <tbody>
                            @foreach (array_reverse($e->hist) as $h)
                                <tr><td>{{ $h['mes'] }}</td><td>{{ $h['abertos'] }}</td><td>{{ $h['reabertos'] }}</td><td>{{ $h['sla'] }}</td><td>{{ $h['uso'] }}</td><td>{{ $h['recl'] }}</td><td>{{ $h['atraso'] }}</td><td>{{ $h['reunioes'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="li-rail">
            <section class="li-card li-pad">
                <h2>Empresas parecidas que cancelaram</h2>
                <p class="li-muted">Perfil dos últimos 3 meses comparado ao de quem já saiu.</p>
                <ul class="li-list">
                    @foreach ($e->similares as $s)
                        <li>
                            <a href="{{ EmpresaResource::getUrl('view', ['record' => $s['codigo']]) }}">
                                <span class="li-mini" aria-hidden="true">{{ mb_strtoupper(mb_substr($s['nome'], 0, 1)).ltrim(substr($s['codigo'], 1), '0') }}</span>
                                <span><strong>{{ $s['nome'] }}</strong><small>Cancelou em {{ $s['mes_cancel'] }} · {{ $s['sim'] }}% de semelhança</small></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="li-card li-pad">
                <h2>Satisfação (NPS)</h2>
                <div class="li-nps">
                    @foreach ($e->nps as $n)
                        <div><small>{{ $n['mes'] }}</small><b class="{{ $n['nota'] === '—' ? 'sem' : ((int) $n['nota'] >= 9 ? 'pro' : ((int) $n['nota'] >= 7 ? 'neu' : 'det')) }}">{{ $n['nota'] }}</b></div>
                    @endforeach
                </div>
                <p class="li-muted">— = convidado e não respondeu.</p>
            </section>
        </aside>
    </div>

    <a class="li-fab" href="{{ $chat }}" aria-label="Abrir o chat com a IA de {{ $e->nome }}">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12c0 4-4 8-9 8a9.9 9.9 0 0 1-4-.8L3 20l1.3-3.9A7.6 7.6 0 0 1 3 12c0-4 4-8 9-8s9 4 9 8z"/></svg>
        Chat com a IA
    </a>
</x-filament-panels::page>
