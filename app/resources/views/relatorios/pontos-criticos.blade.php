<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de pontos críticos — {{ $empresa->nome }}</title>
    <style>
        @php
            $nivelCor = ['Crítico' => '#dc2626', 'Alto' => '#ea580c', 'Médio' => '#2563eb', 'Baixo' => '#16a34a'][$empresa->rotulo()] ?? '#64748b';
        @endphp
        html, body { margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 14px; color: #1e293b; }
        h1 { font-size: 40px; margin: 0 0 12px; }
        h2 { font-size: 26px; margin: 0; color: #0f172a; }
        p { margin: 0; }
        .eyebrow { text-transform: uppercase; letter-spacing: 3px; font-size: 13px; color: #64748b; font-weight: bold; }

        /* Uma seção = uma página inteira; o padding fica só no wrapper interno, nunca na própria seção
           (evita o DOMPDF calcular a largura errado ao somar padding a uma largura já exata em polegadas). */
        .slide { width: 13.333in; height: 7.5in; page-break-after: always; }
        .slide-final { page-break-after: avoid; }
        .slide-body { padding: 0.5in 0.8in; }
        .slide-title { border-bottom: 4px solid {{ $nivelCor }}; padding-bottom: 12px; margin-bottom: 26px; }
        .slide-title p { margin: 6px 0 0; color: #64748b; font-size: 13px; }

        /* Capa: nada de display:table (o DOMPDF nem sempre respeita width:100% em tabelas de 1
           coluna) — centraliza com text-align direto na própria seção, que já é a página inteira.
           O padding-top fica num wrapper à parte: somado à altura fixa da .slide, ele estoura a
           página e gera uma página extra em branco. */
        .capa { text-align: center; }
        .capa-body { padding-top: 2.3in; }
        .capa .sub { color: #475569; font-size: 16px; margin: 10px 0 26px; }
        .capa .badge { display: inline-block; padding: 12px 30px; border-radius: 999px; font-weight: bold; font-size: 18px; color: #fff; background: {{ $nivelCor }}; }
        .capa .rodape { margin-top: 50px; color: #94a3b8; font-size: 12px; }

        /* KPIs */
        .kpi-grid { display: table; width: 100%; table-layout: fixed; margin-top: 26px; }
        .kpi-row { display: table-row; }
        .kpi-cell { display: table-cell; padding-right: 16px; }
        .kpi-cell:last-child { padding-right: 0; }
        .kpi { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 26px 16px; text-align: center; }
        .kpi .valor { font-size: 28px; font-weight: bold; color: #0f172a; }
        .kpi .rotulo { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-top: 8px; }

        /* Métricas prioritárias (barras) */
        .cols-2 { column-count: 2; column-gap: 40px; }
        .metric-row { margin-bottom: 20px; page-break-inside: avoid; }
        .metric-head { display: table; width: 100%; margin-bottom: 5px; }
        .metric-head .nome { display: table-cell; font-weight: bold; font-size: 13px; }
        .metric-head .pontos { display: table-cell; text-align: right; font-size: 11px; color: #64748b; }
        .bar-track { background: #e2e8f0; border-radius: 7px; height: 13px; overflow: hidden; }
        .bar-fill { height: 13px; border-radius: 7px; }

        /* Pontos críticos, cada um com a análise da IA logo abaixo */
        .ponto { margin-bottom: 18px; page-break-inside: avoid; }
        .sinal { padding: 12px 14px; border-radius: 8px 8px 0 0; background: #f8fafc; border-left: 4px solid #dc2626; }
        .sinal .pts { float: right; color: #dc2626; font-weight: bold; font-size: 11px; }
        .sinal strong { font-size: 13px; }
        .sinal p { margin: 5px 0 0; font-size: 12px; }
        .sinal .acao { color: #166534; margin-top: 5px; }
        .ponto-analise { padding: 10px 14px; border-radius: 0 0 8px 8px; background: #eff6ff; border-left: 4px solid #93c5fd; font-size: 12px; line-height: 1.45; font-style: italic; }

        .obs { background: #fefce8; border: 1px solid #fde68a; padding: 10px 12px; border-radius: 8px; font-style: italic; margin-bottom: 18px; font-size: 12px; }

        /* Ações */
        .acao-item { display: table; width: 100%; margin-bottom: 14px; }
        .acao-num { display: table-cell; width: 30px; height: 30px; border-radius: 999px; background: {{ $nivelCor }}; color: #fff; text-align: center; vertical-align: middle; font-weight: bold; font-size: 13px; }
        .acao-texto { display: table-cell; padding-left: 14px; vertical-align: middle; font-size: 13px; }

        .footer { margin-top: 26px; font-size: 10px; color: #94a3b8; }
    </style>
</head>
<body>

    {{-- Slide 1: capa --}}
    <section class="slide capa">
        <div class="capa-body">
            <div class="eyebrow">Relatório de pontos críticos</div>
            <h1>{{ $empresa->nome }}</h1>
            <p class="sub">{{ $empresa->segmento }} &middot; porte {{ $empresa->porte }} &middot; plano {{ $empresa->plano }} &middot; cliente desde {{ \Illuminate\Support\Carbon::parse($empresa->inicio)->format('m/Y') }}</p>
            <div class="badge">{{ $empresa->rotulo() }}{{ $empresa->cancelada() ? '' : ' · score '.$empresa->score.'/100' }}</div>
            <p class="rodape">Gerado em {{ $geradoEm->format('d/m/Y H:i') }} pelo InovaApps</p>
        </div>
    </section>

    {{-- Slide 2: indicadores-chave --}}
    <section class="slide">
        <div class="slide-body">
            <div class="slide-title"><h2>Indicadores-chave</h2><p>Visão rápida do contrato e da exposição atual</p></div>
            <div class="kpi-grid">
                <div class="kpi-row">
                    <div class="kpi-cell"><div class="kpi"><div class="valor">{{ $empresa->rotulo() }}</div><div class="rotulo">Nível</div></div></div>
                    <div class="kpi-cell"><div class="kpi"><div class="valor">{{ $empresa->score }}/100</div><div class="rotulo">Score</div></div></div>
                    <div class="kpi-cell"><div class="kpi"><div class="valor">{{ \App\Models\Customer::brl($empresa->valor) }}</div><div class="rotulo">Contrato/mês</div></div></div>
                    <div class="kpi-cell"><div class="kpi"><div class="valor">{{ \App\Models\Customer::brl($empresa->exposicao) }}</div><div class="rotulo">Exposição mensal</div></div></div>
                </div>
            </div>
            <p class="footer">
                Situação: {{ $empresa->cancelada() ? 'Cancelada em '.$empresa->mes_cancel : 'Ativa' }}.
                Score: soma das parcelas dos sinais, ponderadas pelos pesos configurados. Exposição: score ÷ 100 × valor mensal do contrato.
                São indicadores para priorização; não representam probabilidade de cancelamento nem perda financeira prevista.
            </p>
        </div>
    </section>

    {{-- Slide 3: métricas que a empresa prioriza --}}
    <section class="slide">
        <div class="slide-body">
            <div class="slide-title"><h2>Métricas que {{ $empresa->nome }} prioriza</h2><p>Com base na configuração de prioridades desta empresa — só as métricas com peso ativo entram aqui</p></div>
            <div class="cols-2">
                @forelse ($metricas as $m)
                    @php $pct = min(100, round($m['intensidade'] * 100)); @endphp
                    <div class="metric-row">
                        <div class="metric-head">
                            <span class="nome">{{ $m['rotulo'] }}</span>
                            <span class="pontos">+{{ number_format($m['pontos'], 1, ',', '.') }} pts (peso {{ $m['peso'] }})</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $pct }}%; background: {{ $pct >= 60 ? '#dc2626' : ($pct >= 30 ? '#ea580c' : '#16a34a') }};"></div>
                        </div>
                    </div>
                @empty
                    <p>Nenhuma métrica priorizada configurada para esta empresa.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Slide 4: pontos críticos, cada um com a análise da IA logo abaixo --}}
    <section class="slide">
        <div class="slide-body">
            <div class="slide-title"><h2>Pontos críticos e análise</h2><p>Cada ponto escolhido, com a leitura da IA logo abaixo</p></div>

            @if ($observacoes)
                <p class="obs">O que foi pedido para destacar: {{ $observacoes }}</p>
            @endif

            @forelse ($itens as $item)
                <div class="ponto">
                    <div class="sinal">
                        <span class="pts">+{{ $item['sinal']['pts'] }} pts</span>
                        <strong>{{ $item['sinal']['label'] }}</strong>
                        <p>{{ $item['sinal']['texto'] }}</p>
                        <p class="acao">Ação recomendada: {{ $item['sinal']['acao'] }}</p>
                    </div>
                    <p class="ponto-analise">{{ $item['analise'] }}</p>
                </div>
            @empty
                <p>Nenhum ponto crítico selecionado.</p>
            @endforelse
        </div>
    </section>

    {{-- Slide 5: próximos passos --}}
    <section class="slide slide-final">
        <div class="slide-body">
            <div class="slide-title"><h2>Próximos passos recomendados</h2><p>Uma ação por ponto crítico, em ordem de prioridade</p></div>
            @forelse ($itens as $i => $item)
                <div class="acao-item">
                    <div class="acao-num">{{ $i + 1 }}</div>
                    <div class="acao-texto"><strong>{{ $item['sinal']['label'] }}:</strong> {{ $item['sinal']['acao'] }}</div>
                </div>
            @empty
                <p>Nenhuma ação recomendada — nenhum ponto crítico foi selecionado.</p>
            @endforelse

            <p class="footer">
                Relatório gerado automaticamente pelo InovaApps a partir dos dados de risco calculados para {{ $empresa->nome }}.
                A análise é assistida por IA e deve ser validada por um responsável antes de decisões definitivas.
            </p>
        </div>
    </section>
</body>
</html>
