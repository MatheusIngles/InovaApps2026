@php
    $n = fn ($v, $d = 0) => number_format($v, $d, ',', '.');
    $meses = fn ($v) => $v === null ? '—' : $n($v, $v == floor($v) ? 0 : 1).($v == 1 ? ' mês' : ' meses');
@endphp
<section class="ev" aria-labelledby="ev-resumo">
    <h2 id="ev-resumo" class="ui-h2">O alerta funciona? O que o histórico mostra
        <details class="ui-tip"><summary aria-label="Como estes números foram calculados">?</summary><span class="ui-tip-content">Para cada cliente e cada mês recalculamos o risco como o sistema faria naquele mês. "Alerta" = risco no corte Alto ({{ $atual['limiar'] }}) ou acima. Antecedência = há quantos meses da saída o alerta começou e se manteve até o fim. Alarme falso = parte dos meses de clientes que ficaram em que o risco passaria do corte.</span></details>
    </h2>
    <p class="ui-muted">Com o corte Alto (risco ≥ {{ $atual['limiar'] }}%). O detalhe completo está no relatório de evidências (botão "Gerar relatório de evidências" no topo) e na área de evidências da aba Por segmento.</p>
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
