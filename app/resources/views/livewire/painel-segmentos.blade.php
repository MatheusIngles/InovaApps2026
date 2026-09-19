@php
    use App\Filament\Resources\Empresas\EmpresaResource;
    use App\Support\Risco;

    $n = fn ($v, $d = 0) => number_format($v, $d, ',', '.');
    $brl = fn ($v) => 'R$ '.number_format($v, 0, ',', '.');
@endphp
<div class="ev-aba">
    <section class="ui-card ui-pad ev" aria-labelledby="seg-resumo">
        <h2 id="seg-resumo" class="ui-h2">Cancelamentos por segmento</h2>
        <p class="ui-muted">Escolha um segmento para ver o que estava elevado nos clientes que saíram e quais clientes ativos mostram o mesmo padrão hoje.</p>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>Segmento</th><th>Cancelaram</th><th>Receita perdida/mês</th><th>O que mais se destacou nos cancelados</th><th>Ativos com o mesmo padrão</th></tr></thead>
                <tbody>
                    @foreach ($segmentos as $s)
                        <tr @if ($atual && $atual['segmento'] === $s['segmento']) class="ev-atual" @endif>
                            <td><button type="button" class="ev-link" wire:click="$set('segmento', '{{ $s['segmento'] }}')">{{ $s['segmento'] }}</button></td>
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
                    <div class="ui-sinal-top"><strong>{{ $i['rotulo'] }}</strong>@if ($i['extra'])<span class="ui-muted">fora do score</span>@endif</div>
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
                        <thead><tr><th>Cliente</th><th>Score</th><th>Contrato/mês</th><th>Variáveis elevadas</th></tr></thead>
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
</div>
