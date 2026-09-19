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
@endphp

<x-filament-panels::page>
    <div class="perfil">
        <div class="ui-main">
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
                        <span class="ui-badge {{ $nivelCss }}">{{ $rotulo }}{{ $e->cancelada() ? '' : ' · '.$e->score.'/100' }}</span>
                        <a class="ui-btn primary" href="{{ $chat }}">Conversar com a IA</a>
                        <livewire:relatorio-empresa :codigo="$e->codigo" :key="'relatorio-'.$e->codigo" />
                        <a class="ui-btn" href="{{ EmpresaResource::getUrl() }}">Todas as empresas</a>
                    </div>
                </div>
            </section>

            {{-- Indicadores atuais --}}
            <section class="ui-card ui-pad">
                <h2>Indicadores atuais</h2>
                <dl class="ui-stats">
                    <div><dt>Contrato/mês</dt><dd>{{ Customer::brl($e->valor) }}</dd></div>
                    <div><dt>Uso da plataforma</dt><dd>{{ $ultimo ? $ultimo['uso'].'%' : '—' }}</dd></div>
                    <div><dt>SLA cumprido</dt><dd>{{ $ultimo && is_numeric($ultimo['sla']) ? $ultimo['sla'].'%' : '—' }}</dd></div>
                    <div><dt>Exposição</dt><dd>{{ Customer::brl($e->exposicao) }}</dd></div>
                </dl>
            </section>

            {{-- Destaques: sinais e próximos passos --}}
            <section class="ui-card ui-pad">
                <h2>{{ $e->cancelada() ? 'Sinais antes do cancelamento' : 'Em destaque: sinais de alerta e próximos passos' }}</h2>
                @forelse ($e->sinais as $s)
                    <article class="ui-sinal">
                        <div class="ui-sinal-top"><strong>{{ $s['label'] }}</strong><span>+{{ $s['pts'] }} pts</span></div>
                        <p>{{ $s['texto'] }}</p>
                        @unless ($e->cancelada())<p class="ui-acao">{{ $s['acao'] }}</p>@endunless
                    </article>
                @empty
                    <p class="ui-muted">Nenhum sinal relevante nos últimos 3 meses.</p>
                @endforelse
            </section>

            {{-- Histórico --}}
            <section class="ui-card ui-pad">
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
        </div>

        <aside class="ui-rail">
            <section class="ui-card ui-pad">
                <h2>Empresas parecidas que cancelaram</h2>
                <p class="ui-muted">Perfil dos últimos 3 meses comparado ao de quem já saiu.</p>
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

    <a class="ui-fab" href="{{ $chat }}" aria-label="Abrir o chat com a IA de {{ $e->nome }}">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12c0 4-4 8-9 8a9.9 9.9 0 0 1-4-.8L3 20l1.3-3.9A7.6 7.6 0 0 1 3 12c0-4 4-8 9-8s9 4 9 8z"/></svg>
        Chat com a IA
    </a>
</x-filament-panels::page>
