<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de evidências</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.45; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 20px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #2563eb; }
        h3 { font-size: 12px; margin: 14px 0 4px; }
        p { margin: 0 0 7px; }
        .muted { color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 5px 0 10px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        th { font-size: 10px; color: #64748b; }
        li { margin-bottom: 3px; }
        h2, h3 { page-break-after: avoid; }
        tr, li { page-break-inside: avoid; }
        /* Colunas lado a lado: aproveitam a largura da página em vez de deixar a metade direita vazia. */
        table.grade { table-layout: fixed; margin: 0 0 8px; }
        table.grade > tbody > tr > td, table.grade > tr > td { vertical-align: top; border-bottom: 0; padding: 0 8px 0 0; }
        table.grade > tbody > tr > td + td, table.grade > tr > td + td { padding: 0 0 0 8px; }
        .perfil { display: inline-block; width: 31%; margin-right: 1.5%; vertical-align: top; }
        .kpi { display: inline-block; width: 23%; margin-right: 1%; padding: 8px; background: #f1f5f9; border-radius: 6px; vertical-align: top; }
        .kpi b { display: block; font-size: 16px; color: #1d4ed8; }
    </style>
</head>
<body>
@php
    $n = fn ($v, $d = 0) => number_format($v, $d, ',', '.');
    $brl = fn ($v) => 'R$ '.number_format($v, 0, ',', '.');
    $meses = fn ($v) => $v === null ? '—' : $n($v, $v == floor($v) ? 0 : 1).($v == 1 ? ' mês' : ' meses');
    $forca = fn ($auc) => $auc >= 0.8 ? 'muito forte' : ($auc >= 0.65 ? 'forte' : ($auc >= 0.55 ? 'fraco' : 'quase nenhum'));
    $rotulos = ['medio' => 'Médio', 'alto' => 'Alto', 'critico' => 'Crítico'];
    $alto = $r['limiares']['alto'];
    $vars = collect($r['variaveis'])->sortByDesc('auc')->values();
    $segs = collect($r['segmentos'])->sortByDesc('pct_cancelou')->values();
@endphp
    <h1>Relatório de evidências</h1>
    <p class="muted">{{ $empresa }} · gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>

    <h2>1. O que aconteceu</h2>
    <p>{{ $alto['cancelados'] }} clientes cancelaram e {{ $alto['ativos'] }} seguem ativos. Este relatório olha para o passado dos que saíram para entender o que os diferenciava de quem ficou, e mostra onde ainda há clientes com o mesmo padrão.</p>

    <h2>2. O alerta funciona? (efeito de cada corte)</h2>
    <p>Alerta = atenção igual ou acima do corte do nível. Corte baixo avisa cedo, mas gera mais alertas em clientes que ficaram; corte alto quase não erra, mas avisa tarde ou não avisa. "Acerto" = dos alertas emitidos, quantos viraram cancelamento.</p>
    @if ($r['evidencia_suficiente'])
        <p>Com o corte Alto (atenção ≥ {{ $alto['limiar'] }}), {{ $alto['detectados'] }} dos {{ $alto['cancelados'] }} clientes que cancelaram tinham sido alertados antes de sair{{ $alto['mediana_antecedencia'] !== null ? ', em geral '.$n($alto['mediana_antecedencia'], 1).' meses antes' : '' }}. O preço disso: {{ $n($alto['alarme_falso_pct'], 1) }}% dos meses de clientes que ficaram também passariam do corte (alerta em quem ficou), cerca de {{ $n($alto['alertas_por_mes'], 1) }} clientes por mês.</p>
    @else
        <p>Ainda há poucos cancelamentos para tirar conclusões seguras; use a configuração padrão por enquanto.</p>
    @endif
    <table>
        <tr><th>Corte</th><th>Cancelados alertados</th><th>Com 3+ meses de antecedência</th><th>Antecedência mediana</th><th>Alerta em quem ficou</th><th>Alertas por mês</th><th>Acerto do alerta</th><th>Ativos em alerta hoje</th></tr>
        @foreach ($r['limiares'] as $chave => $l)
            <tr>
                <td>{{ $rotulos[$chave] }} (≥ {{ $l['limiar'] }})</td>
                <td>{{ $l['detectados'] }} de {{ $l['cancelados'] }}</td>
                <td>{{ $l['com_3_meses'] }}</td>
                <td>{{ $meses($l['mediana_antecedencia']) }}</td>
                <td>{{ $n($l['alarme_falso_pct'], 1) }}%</td>
                <td>{{ $n($l['alertas_por_mes'], 1) }}</td>
                <td>{{ $l['precisao_pct'] === null ? '—' : $n($l['precisao_pct'], 1).'%' }}</td>
                <td>{{ $l['ativos_em_alerta_agora'] }} de {{ $l['ativos'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>3. O que mais indica cancelamento</h2>
    <p>"Separação" (AUC) diz o quanto a variável distingue quem cancelou de quem ficou: 0,50 = não distingue; 1,00 = distingue sempre. O peso sugerido é proporcional ao que passa de 0,50. "Cancelados" e "Retidos" são a severidade média (0 a 100%) no último mês antes da saída e no mês mais recente dos ativos; antecedência e alerta em quem ficou usam severidade ≥ 50%.</p>
    <table>
        <tr><th>Variável</th><th>Separação</th><th>Cancelados</th><th>Retidos</th><th>Antecedência</th><th>Alerta em quem ficou</th><th>Peso atual</th><th>Peso sugerido</th></tr>
        @foreach ($vars as $v)
            <tr>
                <td>{{ $v['rotulo'] }}</td>
                <td>{{ $n($v['auc'], 2) }} ({{ $forca($v['auc']) }})</td>
                <td>{{ $n($v['media_cancelados'] * 100) }}%</td>
                <td>{{ $n($v['media_retidos'] * 100) }}%</td>
                <td>{{ $meses($v['antecedencia']) }} ({{ $v['detectados'] }} de {{ $alto['cancelados'] }})</td>
                <td>{{ $n($v['alarme_falso_pct']) }}%</td>
                <td>{{ $n($v['peso_atual']) }}</td>
                <td><b>{{ $n($v['peso_sugerido'], 1) }}</b></td>
            </tr>
        @endforeach
    </table>
    <p>Atenção completa (todos os sinais juntos): separação <b>{{ $n($r['auc_atual'], 3) }}</b> com os pesos atuais e <b>{{ $n($r['auc_sugerido'], 3) }}</b> com os sugeridos.</p>

    <h2>4. Variáveis que a atenção ainda não usa</h2>
    <p>São colunas da planilha que não entram no cálculo da atenção hoje. Testadas na mesma régua: as que separam bem podem valer a pena entrar nele.</p>
    <table class="grade">
        <tr>
            <td style="width: 55%">
                <table>
                    <tr><th>Variável</th><th>Separação</th><th>Cancelados</th><th>Retidos</th></tr>
                    @foreach (collect($r['extras'])->sortByDesc('auc') as $v)
                        <tr><td>{{ $v['rotulo'] }}</td><td>{{ $n($v['auc'], 2) }} ({{ $forca($v['auc']) }})</td><td>{{ $n($v['media_cancelados'], 1) }}</td><td>{{ $n($v['media_retidos'], 1) }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td style="width: 45%">
                <h3 style="margin-top: 0">Perfil de quem cancelou (% da carteira que cancelou)</h3>
                @foreach ($r['perfis'] as $titulo => $grupos)
                    <div style="margin-bottom: 5px"><b>{{ $titulo }}:</b>
                        @foreach ($grupos as $g)
                            {{ $g['nome'] }} <b>{{ $n($g['pct']) }}%</b> <span class="muted">({{ $g['cancelados'] }}/{{ $g['total'] }})</span>{{ $loop->last ? '' : ' · ' }}
                        @endforeach
                    </div>
                @endforeach
            </td>
        </tr>
    </table>

    <h2>4b. Isso vale para o futuro? (validação em período separado)</h2>
    @php($vt = $r['validacao_temporal'])
    @if ($vt['suficiente'])
        <p>Para não avaliar nos mesmos dados usados para calibrar, refizemos o teste como se estivéssemos em {{ substr($vt['corte'], 5, 2) }}/{{ substr($vt['corte'], 0, 4) }}: pesos e corte foram calibrados só com os {{ $vt['treino']['cancelados'] }} cancelamentos até essa data (contra {{ $vt['treino']['ativos'] }} clientes ativos na época) e depois testados nos {{ $vt['teste']['cancelados'] }} cancelamentos seguintes, que o cálculo nunca viu.</p>
        <table>
            <tr><th>Medida</th><th>Resultado</th></tr>
            <tr><td>Separação nos dados de calibração (referência)</td><td>{{ $n($vt['auc']['treino'], 2) }}</td></tr>
            <tr><td>Separação no período de teste, com pesos calibrados antes do corte</td><td><b>{{ $n($vt['auc']['teste_pesos_treino'], 2) }}</b></td></tr>
            <tr><td>Separação no período de teste, com os pesos padrão</td><td>{{ $n($vt['auc']['teste_pesos_padrao'], 2) }}</td></tr>
            <tr><td>Cancelados do teste alertados (corte calibrado antes: atenção ≥ {{ $vt['corte_alto'] ?? '—' }})</td><td><b>{{ $vt['detectados'] }} de {{ $vt['teste']['cancelados'] }}</b></td></tr>
            <tr><td>Alerta entre os que nunca cancelaram</td><td>{{ $n($vt['alarme_falso_pct'], 1) }}%</td></tr>
        </table>
        <p class="muted">Quanto mais perto a separação do teste estiver da de calibração, menos o resultado depende de ter sido ajustado aos mesmos casos. Os clientes que saíram depois do corte contam como ativos na calibração, como na época, o que a torna mais difícil que o teste. Com poucos cancelamentos por período, os números são indicativos.</p>
    @else
        <p>Não há cancelamentos suficientes antes e depois de um ponto de corte para validar em período separado.</p>
    @endif

    <h2>5. Configuração recomendada</h2>
    @if ($r['evidencia_suficiente'])
        <ul>
            <li><b>Ordem das métricas:</b> {{ collect($recomendada['ordem'])->pluck('rotulo')->implode(' › ') }}.</li>
            <li><b>Desligar (não separam cancelados de retidos):</b> {{ $recomendada['desligadas'] ? implode(', ', $recomendada['desligadas']) : 'nenhuma' }}.</li>
            <li><b>Cortes de alerta:</b> Médio ≥ {{ $recomendada['limiares']['medio'] }} (até 30% de alerta em quem ficou), Alto ≥ {{ $recomendada['limiares']['alto'] }} (até 8%), Crítico ≥ {{ $recomendada['limiares']['critico'] }} (até 2%).</li>
            <li><b>Equilíbrio atenção × valor (K):</b> decisão de negócio, ajustável em Configurações.</li>
        </ul>
    @else
        <p>Sem cancelamentos suficientes para recomendar mudanças.</p>
    @endif

    <h2>6. Os {{ count($r['cancelamentos']['alto']) }} cancelamentos, um a um (corte Alto ≥ {{ $alto['limiar'] }})</h2>
    <p>"Sem alerta" = a atenção não estava acima do corte no último mês antes da saída.</p>
    <table class="grade">
        <tr>
            @foreach (collect($r['cancelamentos']['alto'])->chunk(max(1, (int) ceil(count($r['cancelamentos']['alto']) / 2))) as $metade)
                <td style="width: 50%">
                    <table>
                        <tr><th>Cliente</th><th>Saída</th><th>Contrato/mês</th><th>Alerta desde</th><th>Antec.</th><th>Atenção</th></tr>
                        @foreach ($metade as $c)
                            <tr>
                                <td>{{ $c['nome'] }} ({{ $c['codigo'] }})</td>
                                <td>{{ $c['saida'] }}</td>
                                <td>{{ $brl($c['valor']) }}</td>
                                <td>{{ $c['alerta_desde'] ?? 'Sem alerta' }}</td>
                                <td>{{ $meses($c['antecedencia']) }}</td>
                                <td>{{ $c['score_final'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>7. Por segmento</h2>
    <table>
        <tr><th>Segmento</th><th>Cancelaram</th><th>Receita perdida/mês</th><th>O que mais se destacou nos cancelados</th><th>Ativos com o mesmo padrão</th></tr>
        @foreach ($segs as $s)
            <tr>
                <td>{{ $s['segmento'] }}</td>
                <td>{{ $s['cancelados'] }} de {{ $s['total'] }} ({{ $n($s['pct_cancelou']) }}%)</td>
                <td>{{ $brl($s['receita_perdida']) }}</td>
                <td>{{ collect($s['elevados'])->take(2)->pluck('rotulo')->join(', ') ?: '—' }}</td>
                <td>{{ count($s['expostos']) }} ({{ $brl($s['receita_exposta']) }}/mês)</td>
            </tr>
        @endforeach
    </table>

    <table class="grade">
    @foreach ($segs->chunk(2) as $par)
        <tr>
        @foreach ($par as $s)
        <td style="width: 50%">
        <h3 style="margin-top: 6px">{{ $s['segmento'] }}: o que aconteceu e o que pode acontecer</h3>
        <p>{{ $s['cancelados'] }} de {{ $s['total'] }} clientes do segmento cancelaram ({{ $n($s['pct_cancelou']) }}%), o que deixou de render {{ $brl($s['receita_perdida']) }} por mês.</p>
        @if ($s['elevados'])
            <p><b>Elevado nos que cancelaram (último mês antes da saída):</b></p>
            <ul>
                @foreach ($s['elevados'] as $i)
                    <li><b>{{ $i['rotulo'] }}</b>{{ $i['extra'] ? ' (fora da atenção)' : '' }}: {{ $i['texto'] }}.</li>
                @endforeach
            </ul>
        @else
            <p class="muted">Nenhuma variável se destacou de forma clara neste segmento.</p>
        @endif
        @if ($s['expostos'])
            <p><b>Ativos que repetem o padrão hoje</b> ({{ count($s['expostos']) }} clientes, {{ $brl($s['receita_exposta']) }}/mês):</p>
            <table>
                <tr><th>Cliente</th><th>Atenção</th><th>Contrato/mês</th><th>Variáveis elevadas</th></tr>
                @foreach ($s['expostos'] as $e)
                    <tr><td>{{ $e['nome'] }}</td><td>{{ $e['score'] }}</td><td>{{ $brl($e['valor']) }}</td><td>{{ implode(', ', $e['variaveis']) }}</td></tr>
                @endforeach
            </table>
        @else
            <p class="muted">Nenhum cliente ativo deste segmento repete o padrão hoje.</p>
        @endif
        </td>
        @endforeach
        @if ($par->count() < 2)<td style="width: 50%"></td>@endif
        </tr>
    @endforeach
    </table>

    <p class="muted">Os números indicam correlação com o cancelamento, não causa, e não são uma probabilidade de cancelamento.</p>
</body>
</html>
