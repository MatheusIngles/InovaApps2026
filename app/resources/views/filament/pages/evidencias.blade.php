@php
    $n = fn ($v, $d = 0) => number_format($v, $d, ',', '.');
    $meses = fn ($v) => $v === null ? '—' : $n($v, $v == floor($v) ? 0 : 1).($v == 1 ? ' mês' : ' meses');
    $rotulos = ['medio' => 'Médio', 'alto' => 'Alto', 'critico' => 'Crítico'];
@endphp
<x-filament-panels::page>
    <section class="ev" aria-labelledby="ev-limiar">
        <div class="ev-topo">
            <div>
                <h2 id="ev-limiar" class="ui-h2">Quando consideramos que o cliente está em alerta?</h2>
                <p class="ui-muted">Alerta = score igual ou acima do corte do nível. Mude o corte e veja o efeito em tudo abaixo.</p>
            </div>
            <div class="ev-seg" role="group" aria-label="Corte do alerta">
                @foreach ($rotulos as $chave => $rotulo)
                    <button type="button" wire:click="$set('nivel', '{{ $chave }}')" aria-pressed="{{ $nivel === $chave ? 'true' : 'false' }}">{{ $rotulo }} <small>≥ {{ $niveis[$chave] }}</small></button>
                @endforeach
            </div>
        </div>

        <div class="ev-kpis">
            <div class="ui-card ev-kpi">
                <span>Cancelados alertados com 3+ meses de antecedência</span>
                <strong>{{ $atual['com_3_meses'] }} de {{ $atual['cancelados'] }}</strong>
                <small>{{ $atual['detectados'] }} foram alertados em algum momento antes de sair</small>
            </div>
            <div class="ui-card ev-kpi">
                <span>Antecedência mediana do alerta</span>
                <strong>{{ $meses($atual['mediana_antecedencia']) }}</strong>
                <small>tempo entre o alerta (que se manteve) e a saída</small>
            </div>
            <div class="ui-card ev-kpi">
                <span>Alarme falso entre clientes que ficaram</span>
                <strong>{{ $n($atual['alarme_falso_pct'], 1) }}%</strong>
                <small>dos meses de clientes retidos passariam do corte</small>
            </div>
            <div class="ui-card ev-kpi">
                <span>Carga de alertas hoje</span>
                <strong>{{ $atual['ativos_em_alerta_agora'] }} de {{ $atual['ativos'] }}</strong>
                <small>~{{ $n($atual['alertas_por_mes'], 1) }} clientes retidos em alerta por mês</small>
            </div>
        </div>
    </section>

    <section class="ui-card ui-pad ev" aria-labelledby="ev-cortes">
        <h2 id="ev-cortes" class="ui-h2">Efeito de cada corte</h2>
        <p class="ui-muted">Corte baixo avisa cedo, mas gera mais alarme falso; corte alto quase não erra, mas avisa tarde ou não avisa. "Acerto" = dos alertas emitidos, quantos viraram cancelamento em até 6 meses.</p>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Corte</th><th>Cancelados alertados</th><th>Com 3+ meses</th><th>Antecedência mediana</th><th>Alarme falso</th><th>Alertas/mês</th><th>Acerto do alerta</th></tr></thead>
                <tbody>
                    @foreach ($limiares as $chave => $l)
                        <tr @if ($nivel === $chave) class="ev-atual" @endif>
                            <td>{{ $rotulos[$chave] }} (≥ {{ $l['limiar'] }})</td>
                            <td>{{ $l['detectados'] }} de {{ $l['cancelados'] }}</td>
                            <td>{{ $l['com_3_meses'] }}</td>
                            <td>{{ $meses($l['mediana_antecedencia']) }}</td>
                            <td>{{ $n($l['alarme_falso_pct'], 1) }}%</td>
                            <td>{{ $n($l['alertas_por_mes'], 1) }}</td>
                            <td>{{ $l['precisao_pct'] === null ? '—' : $n($l['precisao_pct'], 1).'%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="ui-card ui-pad ev" aria-labelledby="ev-vars">
        <h2 id="ev-vars" class="ui-h2">Quanto cada variável separa quem cancelou de quem ficou</h2>
        <p class="ui-muted">Separação (AUC): 0,50 = não distingue; 1,00 = distingue sempre. O peso sugerido é proporcional ao que passa de 0,50, então uma variável que não separa fica com peso zero.</p>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Variável</th><th>Separação</th><th>Cancelados</th><th>Retidos</th><th>Antecedência</th><th>Alarme falso</th><th>Peso atual</th><th>Peso sugerido</th></tr></thead>
                <tbody>
                    @foreach (collect($variaveis)->sortByDesc('auc') as $v)
                        <tr>
                            <td>{{ $v['rotulo'] }}</td>
                            <td><b>{{ $n($v['auc'], 2) }}</b></td>
                            <td>{{ $n($v['media_cancelados'] * 100) }}%</td>
                            <td>{{ $n($v['media_retidos'] * 100) }}%</td>
                            <td>{{ $meses($v['antecedencia']) }} <small>({{ $v['detectados'] }} de {{ $atual['cancelados'] }})</small></td>
                            <td>{{ $n($v['alarme_falso_pct']) }}%</td>
                            <td>{{ $n($v['peso_atual']) }}</td>
                            <td><b>{{ $n($v['peso_sugerido'], 1) }}</b></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="ui-muted">"Cancelados" e "Retidos" mostram a severidade média (0 a 100%) no último mês antes da saída e no mês mais recente dos ativos. Antecedência e alarme falso usam severidade ≥ 50%.
            Score completo: separação <b>{{ $n($auc_atual, 3) }}</b> com os pesos atuais e <b>{{ $n($auc_sugerido, 3) }}</b> com os sugeridos.</p>
    </section>

    <section class="ui-card ui-pad ev" aria-labelledby="ev-extras">
        <h2 id="ev-extras" class="ui-h2">Variáveis que o score ainda não usa</h2>
        <p class="ui-muted">Testadas na mesma régua. Se separam bem, são candidatas a entrar no score; se não, ficam de fora de propósito.</p>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Variável</th><th>Separação</th><th>Média nos cancelados</th><th>Média nos retidos</th></tr></thead>
                <tbody>
                    @foreach (collect($extras)->sortByDesc('auc') as $v)
                        <tr><td>{{ $v['rotulo'] }}</td><td><b>{{ $n($v['auc'], 2) }}</b></td><td>{{ $n($v['media_cancelados'], 1) }}</td><td>{{ $n($v['media_retidos'], 1) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ev-perfis">
            @foreach ($perfis as $titulo => $grupos)
                <div>
                    <h3>{{ $titulo }}: % que cancelou</h3>
                    <ul>
                        @foreach ($grupos as $g)
                            <li><span>{{ $g['nome'] }}</span><b>{{ $n($g['pct']) }}%</b> <small>({{ $g['cancelados'] }} de {{ $g['total'] }})</small></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <section class="ui-card ui-pad ev" aria-labelledby="ev-canc">
        <h2 id="ev-canc" class="ui-h2">Os {{ count($linhas) }} cancelamentos, um a um</h2>
        <p class="ui-muted">Com o corte {{ $rotulos[$nivel] }} (≥ {{ $niveis[$nivel] }}). "Sem alerta" = o score não estava acima do corte no último mês antes da saída.</p>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Cliente</th><th>Saída</th><th>Contrato/mês</th><th>Alerta desde</th><th>Antecedência</th><th>Score no último mês</th></tr></thead>
                <tbody>
                    @foreach ($linhas as $l)
                        <tr>
                            <td>{{ $l['nome'] }}</td>
                            <td>{{ $l['saida'] }}</td>
                            <td>R$ {{ $n($l['valor']) }}</td>
                            <td>{{ $l['alerta_desde'] ?? 'Sem alerta' }}</td>
                            <td>{{ $meses($l['antecedencia']) }}</td>
                            <td>{{ $l['score_final'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <p class="ui-muted ev-limite">Limites: são poucos eventos (os cancelamentos da base) e a análise usa os mesmos dados que calibraram os pesos, então os números são um indicativo, não uma garantia. O acerto do alerta considera só meses com 6 meses seguintes já observados.</p>
</x-filament-panels::page>
