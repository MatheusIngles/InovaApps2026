<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de pontos críticos — {{ $empresa->nome }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 24px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .meta { color: #64748b; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; font-size: 10px; }
        .sinal { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; }
        .sinal .pts { float: right; color: #dc2626; font-weight: bold; font-size: 10px; }
        .sinal strong { font-size: 11px; }
        .sinal .acao { color: #166534; margin-top: 2px; }
        .analise p { text-align: justify; line-height: 1.5; margin: 0 0 10px; }
        .obs { background: #f1f5f9; padding: 8px; border-radius: 4px; font-style: italic; margin-top: 8px; }
        .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    <h1>Relatório de pontos críticos</h1>
    <p class="meta">
        {{ $empresa->nome }} ({{ $empresa->codigo }}) &middot; {{ $empresa->segmento }}, porte {{ $empresa->porte }}, plano {{ $empresa->plano }}
        &middot; Gerado em {{ $geradoEm->format('d/m/Y H:i') }}
    </p>

    <table>
        <thead>
            <tr><th>Nível</th><th>Score</th><th>Contrato/mês</th><th>Exposição mensal</th><th>Situação</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $empresa->rotulo() }}</td>
                <td>{{ $empresa->score }}/100</td>
                <td>{{ \App\Models\Customer::brl($empresa->valor) }}</td>
                <td>{{ \App\Models\Customer::brl($empresa->exposicao) }}</td>
                <td>{{ $empresa->cancelada() ? 'Cancelada em '.$empresa->mes_cancel : 'Ativa' }}</td>
            </tr>
        </tbody>
    </table>
    <p class="meta">
        Score: soma das oito parcelas dos sinais, ponderadas pelos pesos configurados e arredondada para inteiro.
        Exposição: {{ $empresa->score }} ÷ 100 × {{ \App\Models\Customer::brl($empresa->valor) }}/mês.
        São indicadores para priorização; não representam probabilidade de cancelamento nem perda financeira prevista.
    </p>
    <h2>Composição do score</h2>
    <table>
        <thead><tr><th>Sinal</th><th>Métrica</th><th>Ajuste prioridade</th><th>Total</th></tr></thead>
        <tbody>
            @foreach ($empresa->contribuicoesScore() as $parcela)
                <tr><td>{{ $parcela['rotulo'] }}</td><td>{{ number_format($parcela['base'], 1, ',', '.') }}</td><td>{{ number_format($parcela['ajuste_prioridade'], 1, ',', '.') }}</td><td>{{ number_format($parcela['pontos'], 1, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Análise</h2>
    <div class="analise">
        @foreach (preg_split('/\n{2,}/', trim($analise)) as $paragrafo)
            @continue(trim($paragrafo) === '')
            <p>{{ trim($paragrafo) }}</p>
        @endforeach
    </div>

    @if ($observacoes)
        <p class="obs">Observação considerada na análise: {{ $observacoes }}</p>
    @endif

    <h2>Pontos críticos selecionados</h2>
    @forelse ($sinais as $s)
        <div class="sinal">
            <span class="pts">+{{ $s['pts'] }} pts</span>
            <strong>{{ $s['label'] }}</strong>
            <p>{{ $s['texto'] }}</p>
            <p class="acao">Ação recomendada: {{ $s['acao'] }}</p>
        </div>
    @empty
        <p>Nenhum ponto crítico selecionado.</p>
    @endforelse

    <p class="footer">
        Relatório gerado automaticamente pelo InovaApps a partir dos dados de risco calculados para {{ $empresa->nome }}.
        A análise é assistida por IA e deve ser validada por um responsável antes de decisões definitivas.
    </p>
</body>
</html>
