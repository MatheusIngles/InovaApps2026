@php
    use App\Filament\Resources\Empresas\EmpresaResource;
    use App\Support\Risco;

    $n = fn ($v, $d = 0) => number_format($v, $d, ',', '.');
    $brl = fn ($v) => 'R$ '.number_format($v, 0, ',', '.');
@endphp
<div class="ev-aba">
    <section class="ui-card ui-pad ev" aria-labelledby="seg-resumo">
        <h2 id="seg-resumo" class="ui-h2">Cancelamentos por segmento</h2>
        <p class="ui-muted">Escolha um segmento (o número é o % que cancelou) para ver o que estava elevado nos clientes que saíram e quais clientes ativos mostram o mesmo padrão hoje.</p>
        <div class="ev-seg ev-seg-multi" role="group" aria-label="Escolher segmento">
            @foreach ($segmentos as $s)
                <button type="button" wire:click="$set('segmento', '{{ $s['segmento'] }}')" aria-pressed="{{ $atual && $atual['segmento'] === $s['segmento'] ? 'true' : 'false' }}">{{ $s['segmento'] }} <small>{{ $n($s['pct_cancelou']) }}%</small></button>
            @endforeach
        </div>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Segmento</th><th>Cancelaram</th><th>Receita perdida/mês</th><th>O que mais se destacou nos cancelados</th><th>Ativos com o mesmo padrão</th></tr></thead>
                <tbody>
                    @foreach ($segmentos as $s)
                        <tr wire:click="$set('segmento', '{{ $s['segmento'] }}')" class="ev-linha @if ($atual && $atual['segmento'] === $s['segmento']) ev-atual @endif">
                            <td>{{ $s['segmento'] }}</td>
                            <td>{{ $s['cancelados'] }} de {{ $s['total'] }} ({{ $n($s['pct_cancelou']) }}%)</td>
                            <td>{{ $brl($s['receita_perdida']) }}</td>
                            <td>{{ collect($s['elevados'])->take(2)->pluck('rotulo')->join(', ') ?: '—' }}</td>
                            <td>{{ count($s['expostos']) }} ({{ $brl($s['receita_exposta']) }}/mês)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($atual)
        <section class="ui-card ui-pad ev" aria-labelledby="seg-detalhe">
            <h2 id="seg-detalhe" class="ui-h2">{{ $atual['segmento'] }}: o que aconteceu e o que pode acontecer</h2>
            <p>{{ $atual['cancelados'] }} de {{ $atual['total'] }} clientes do segmento cancelaram ({{ $n($atual['pct_cancelou']) }}%), o que deixou de render {{ $brl($atual['receita_perdida']) }} por mês.</p>

            <h3 class="ev-h3">O que estava elevado nos que cancelaram (último mês antes da saída)</h3>
            @forelse ($atual['elevados'] as $i)
                <article class="ui-sinal">
                    <div class="ui-sinal-top"><strong>{{ $i['rotulo'] }}</strong>@if ($i['extra'])<span class="ui-muted">fora do risco <details class="ui-tip"><summary aria-label="Por que está fora do risco">?</summary><span class="ui-tip-content">A atenção usa só os oito sinais configurados. Esta variável é guardada, mas não soma pontos. Aqui ela só aparece se a média dos cancelados for pelo menos 50% acima da dos que ficaram.</span></details></span>@endif</div>
                    <p>{{ ucfirst($i['texto']) }}.</p>
                    @if (! $i['extra'] && Risco::acao($i['k']))<p class="ui-acao">{{ Risco::acao($i['k']) }}</p>@endif
                </article>
            @empty
                <p class="ui-muted">Nenhuma variável se destacou de forma clara neste segmento.</p>
            @endforelse

            <h3 class="ev-h3">Quem ainda está ativo e mostra o mesmo padrão</h3>
            @if ($atual['expostos'])
                <p class="ui-muted">{{ count($atual['expostos']) }} clientes ativos de {{ $atual['segmento'] }} repetem hoje uma ou mais dessas variáveis, somando {{ $brl($atual['receita_exposta']) }} por mês. Se seguirem o caminho dos que saíram, é essa receita que está em jogo.</p>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Cliente</th><th>Atenção</th><th>Contrato/mês</th><th>Variáveis elevadas</th></tr></thead>
                        <tbody>
                            @foreach ($atual['expostos'] as $e)
                                <tr>
                                    <td><a href="{{ EmpresaResource::getUrl('view', ['record' => $e['codigo']]) }}">{{ $e['nome'] }}</a></td>
                                    <td>{{ $e['score'] }}</td>
                                    <td>{{ $brl($e['valor']) }}</td>
                                    <td>{{ implode(', ', $e['variaveis']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="ui-muted">Nenhum cliente ativo deste segmento repete o padrão hoje.</p>
            @endif
        </section>
    @endif

    <section class="ui-card ui-pad ev" aria-labelledby="seg-evid">
        <h2 id="seg-evid" class="ui-h2">Evidências da carteira</h2>
        <p class="ui-muted">O resumo completo, com todos os números explicados, está no botão "Gerar relatório de evidências" no topo do painel (chega por notificação).</p>

        <h3 class="ev-h3">Variáveis que a atenção ainda não usa</h3>
        <p class="ui-muted">Colunas da planilha que não somam pontos na atenção. As que separam bem podem valer a pena entrar nele.</p>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Variável</th><th>Separação <details class="ui-tip"><summary aria-label="Como a separação foi calculada">?</summary><span class="ui-tip-content">Para cada variável, usamos a média dos 3 últimos meses de cada cliente. Comparamos, par a par, cada cliente que cancelou com cada um que ficou. A separação é a fração dos pares em que o cancelado tem o valor maior (empate conta meio ponto): 0,50 = não distingue; 1,00 = sempre distingue (AUC). Mostra correlação com o cancelamento, não causa.</span></details></th><th>Média nos cancelados</th><th>Média nos retidos</th></tr></thead>
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

        @if ($validacao_temporal['suficiente'])
            <h3 class="ev-h3">Isso vale para o futuro?</h3>
            <p class="ui-muted">Calibrando só com os {{ $validacao_temporal['treino']['cancelados'] }} cancelamentos até {{ substr($validacao_temporal['corte'], 5, 2) }}/{{ substr($validacao_temporal['corte'], 0, 4) }} e testando nos {{ $validacao_temporal['teste']['cancelados'] }} seguintes, o alerta pegou <b>{{ $validacao_temporal['detectados'] }} de {{ $validacao_temporal['teste']['cancelados'] }}</b> com {{ $n($validacao_temporal['alarme_falso_pct'], 1) }}% de alerta em quem ficou (separação {{ $n($validacao_temporal['auc']['teste_pesos_treino'], 2) }} no teste contra {{ $n($validacao_temporal['auc']['treino'], 2) }} na calibração). Detalhes no relatório de evidências.</p>
        @endif

        @if ($evidencia_suficiente)
            <h3 class="ev-h3">Configuração recomendada</h3>
            <ul class="ev-lista">
                <li><b>Ordem das métricas:</b> {{ collect($recomendada['ordem'])->pluck('rotulo')->implode(' › ') }}.</li>
                <li><b>Desligar:</b> {{ $recomendada['desligadas'] ? implode(', ', $recomendada['desligadas']) : 'nenhuma' }}.</li>
                <li><b>Cortes de alerta:</b> Médio ≥ {{ $recomendada['limiares']['medio'] }}, Alto ≥ {{ $recomendada['limiares']['alto'] }}, Crítico ≥ {{ $recomendada['limiares']['critico'] }}.</li>
            </ul>
            <div class="ui-actions">
                <button type="button" class="ui-btn primary" wire:click="aplicarConfiguracaoRecomendada" wire:confirm="Aplica a ordem das métricas e os cortes recomendados e recalcula a atenção de todos os clientes. Dá para voltar em Configurações. Continuar?">Aplicar configuração recomendada</button>
            </div>
        @endif
    </section>
</div>
