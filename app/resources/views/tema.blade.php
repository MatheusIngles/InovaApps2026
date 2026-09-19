{{-- Tema azul do painel: topbar, fundo, cards, login, perfil da empresa e chatbot. Usa as cores primárias do Filament (Color::Blue). --}}
@php
    $empresa = app(\App\Support\Tenancy\CompanyContext::class)->current() ?? \App\Support\Tenancy\TenantResolver::company(request());
    $secundaria = $empresa?->tema()['secondary'] ?? \App\Models\Company::TEMA_PADRAO['secondary'];
    [$r, $g, $b] = sscanf($secundaria, '#%02x%02x%02x');
    $textoSobre = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > 150 ? '#0f172a' : '#ffffff'; // texto legível sobre qualquer cor
@endphp
<style>
    :root { --tenant-secondary: {{ $secundaria }}; --on-secondary: {{ $textoSobre }}; }
    :root { --card-shadow: 0 1px 2px rgb(15 41 107 / .08), 0 0 0 1px var(--primary-100); }

    ::selection { background: var(--primary-200); color: var(--primary-950); }
    input, textarea { caret-color: var(--primary-600); }
    .chatbot-log, .li-table-wrap { scrollbar-width: thin; scrollbar-color: var(--primary-300) transparent; }
    .li-btn:focus-visible, .chatbot-sug button:focus-visible, .chatbot-form button:focus-visible { outline: 3px solid var(--primary-300); outline-offset: 2px; }
    .chatbot-clear:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }

    /* Fundo azulado em vez de branco chapado */
    .fi-body, .fi-layout { background: var(--primary-50); }
    .fi-main { background: transparent; }

    /* Top bar azul */
    .fi-topbar-ctn { background: var(--tenant-secondary); }
    .fi-topbar { background: transparent; box-shadow: none; --tw-ring-shadow: 0 0 #0000; }
    .fi-topbar, .fi-topbar a, .fi-topbar button, .fi-topbar .fi-logo, .fi-topbar-item-label, .fi-topbar-item-icon { color: var(--on-secondary); }
    .fi-topbar .fi-logo { font-weight: 800; letter-spacing: -.01em; }
    .fi-topbar .fi-dropdown-panel :is(a, button, span, svg, p) { color: var(--gray-700); }
    .fi-topbar .fi-dropdown-panel .fi-dropdown-list-item:hover { background: var(--primary-50); }
    .fi-topbar-item-btn { border-radius: .5rem; }
    .fi-topbar :is(a, button):focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .fi-topbar-item-btn:hover { background: rgb(255 255 255 / .14); }
    .fi-topbar-item.fi-active .fi-topbar-item-btn { background: rgb(255 255 255 / .22); }
    .fi-topbar-item.fi-active .fi-topbar-item-label { font-weight: 700; }

    /* Cards brancos com contorno azul suave sobre o fundo azulado */
    .fi-section, .fi-ta-ctn, .fi-wi-chart, .fi-ta-content-grid .fi-ta-record { box-shadow: var(--card-shadow); border: 0; }

    /* KPIs em azul sólido */
    .fi-wi-stats-overview-stat { background: var(--primary-600); box-shadow: 0 1px 3px rgb(15 41 107 / .25); border: 0; }
    .fi-wi-stats-overview-stat * { color: #fff !important; }

    /* Login: fundo em gradiente azul */
    .fi-simple-layout { background: linear-gradient(155deg, var(--tenant-secondary), var(--primary-600) 60%, var(--primary-500)); }

    /* ---------- Perfil da empresa (estilo LinkedIn) ---------- */
    .li { display: grid; grid-template-columns: minmax(0, 1fr) 21rem; gap: 1.25rem; align-items: start; }
    .li-main, .li-rail { display: flex; flex-direction: column; gap: 1.25rem; min-width: 0; }
    @media (max-width: 1024px) { .li { grid-template-columns: 1fr; padding-bottom: 5rem; } }
    @media (max-width: 640px) { .li-fab { right: 1rem; bottom: 1rem; padding: .85rem 1.1rem; } .li-actions { align-self: stretch; } .li-title h1 { font-size: 1.35rem; } }
    .li-card { background: #fff; border-radius: .75rem; box-shadow: var(--card-shadow); overflow: hidden; }
    .li-pad { padding: 1.25rem 1.5rem; }
    .li-card h2 { font-size: 1.05rem; font-weight: 700; color: var(--primary-900); margin: 0 0 .75rem; }
    .li-banner { height: 10rem; background: linear-gradient(120deg, var(--tenant-secondary), var(--primary-500)); }
    .li-head { position: relative; display: flex; flex-wrap: wrap; gap: 1rem 1.5rem; justify-content: space-between; padding: 0 1.5rem 1.5rem; }
    .li-logo { width: 7.5rem; height: 7.5rem; margin-top: -3.75rem; flex: none; display: grid; place-items: center; border-radius: .75rem; background: #fff; border: 4px solid #fff; box-shadow: 0 2px 8px rgb(15 41 107 / .2); font-size: 2rem; font-weight: 800; letter-spacing: -.02em; color: var(--primary-700); background-image: linear-gradient(135deg, var(--primary-50), #fff); }
    .li-title { flex: 1 1 16rem; padding-top: .25rem; }
    .li-title h1 { font-size: 1.6rem; font-weight: 800; color: var(--primary-950); line-height: 1.2; margin: 0; }
    .li-headline { margin: .25rem 0 0; color: var(--gray-700); font-size: 1rem; }
    .li-meta, .li-muted { margin: .25rem 0 0; color: var(--gray-500); font-size: .85rem; }
    .li-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; align-self: flex-end; }
    .li-btn { display: inline-flex; align-items: center; min-height: 2.75rem; padding: .5rem 1.1rem; border-radius: 999px; font-weight: 600; font-size: .9rem; color: var(--primary-700); border: 1.5px solid var(--primary-600); text-decoration: none; }
    .li-btn:hover { background: var(--primary-50); }
    .li-btn.primary { background: var(--primary-600); color: #fff; }
    .li-btn.primary:hover { background: var(--primary-700); }
    .li-badge { padding: .3rem .8rem; border-radius: 999px; font-size: .8rem; font-weight: 700; }
    .li-badge.crit { background: #fee2e2; color: #b91c1c; } .li-badge.alto { background: #ffedd5; color: #c2410c; }
    .li-badge.med { background: var(--primary-100); color: var(--primary-800); } .li-badge.baixo { background: #dcfce7; color: #15803d; }
    .li-badge.canc { background: var(--gray-200); color: var(--gray-700); }
    .li-about { color: var(--gray-700); line-height: 1.6; margin: 0 0 1rem; }
    .li-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); margin: 0; border-top: 1px solid var(--primary-100); padding-top: 1rem; }
    .li-stats div { padding: 0 1rem; border-left: 1px solid var(--primary-100); }
    .li-stats div:first-child { padding-left: 0; border-left: 0; }
    .li-stats dt { font-size: .75rem; color: var(--primary-800); font-weight: 600; }
    .li-stats dd { margin: .15rem 0 0; font-size: 1.25rem; font-weight: 800; color: var(--primary-950); }
    .li-sinal { padding: .85rem 0; border-top: 1px solid var(--primary-100); }
    .li-sinal:first-of-type { border-top: 0; padding-top: 0; }
    .li-sinal-top { display: flex; justify-content: space-between; gap: 1rem; color: var(--primary-900); }
    .li-sinal-top span { color: #b91c1c; font-weight: 700; font-size: .85rem; }
    .li-sinal p { margin: .2rem 0 0; color: var(--gray-700); font-size: .9rem; }
    .li-sinal .li-acao { color: var(--primary-700); font-weight: 600; }
    .li-table-wrap { overflow-x: auto; }
    .li-table { width: 100%; border-collapse: collapse; font-size: .85rem; min-width: 36rem; }
    .li-table th { text-align: left; padding: .5rem .6rem; background: var(--primary-50); color: var(--primary-800); font-weight: 700; white-space: nowrap; }
    .li-table td { padding: .5rem .6rem; border-top: 1px solid var(--primary-100); color: var(--gray-700); font-variant-numeric: tabular-nums; }
    .li-list { list-style: none; margin: .75rem 0 0; padding: 0; display: flex; flex-direction: column; gap: .25rem; }
    .li-list a { display: flex; gap: .75rem; align-items: center; padding: .5rem; border-radius: .6rem; text-decoration: none; color: var(--primary-950); }
    .li-list a:hover { background: var(--primary-50); }
    .li-list strong { display: block; font-size: .9rem; } .li-list small { color: var(--gray-500); font-size: .78rem; }
    .li-mini { width: 2.5rem; height: 2.5rem; flex: none; display: grid; place-items: center; border-radius: .5rem; background: var(--primary-100); color: var(--primary-700); font-weight: 800; font-size: .85rem; }
    .li-nps { display: grid; grid-template-columns: repeat(auto-fill, minmax(3.6rem, 1fr)); gap: .4rem; margin: .5rem 0; }
    .li-nps div { text-align: center; background: var(--primary-50); border-radius: .5rem; padding: .35rem 0; }
    .li-nps small { display: block; font-size: .65rem; color: var(--gray-500); }
    .li-nps b { font-size: 1.1rem; } .li-nps .pro { color: #15803d; } .li-nps .neu { color: #b45309; } .li-nps .det { color: #b91c1c; } .li-nps .sem { color: var(--gray-400); }

    /* Botão flutuante de acesso rápido ao chat */
    .li-fab { position: fixed; right: 1.5rem; bottom: 1.5rem; z-index: 40; display: inline-flex; align-items: center; gap: .6rem; padding: .95rem 1.4rem; border-radius: 999px; background: var(--primary-600); color: #fff; font-weight: 700; text-decoration: none; box-shadow: 0 8px 24px rgb(29 78 216 / .45); transition: background .15s; }
    .li-fab:hover { background: var(--primary-700); }
    .li-fab:focus-visible { outline: 3px solid var(--primary-300); outline-offset: 3px; }

    /* ---------- Chatbot: a tela toda abaixo da top bar ---------- */
    .fi-main:has(.chatbot) { padding: 0; }
    .chatbot { position: fixed; z-index: 10; inset: 4rem 0 0 0; display: flex; flex-direction: column; background: #fff; }
    .chatbot-head { display: flex; flex-wrap: wrap; gap: .75rem 1rem; align-items: center; justify-content: space-between; padding: .85rem clamp(1rem, 4vw, 2rem); background: var(--tenant-secondary); color: var(--on-secondary); }
    .chatbot-id { display: flex; align-items: center; gap: .75rem; } .chatbot-id strong { font-size: 1.05rem; } .chatbot-id small { display: block; opacity: .85; }
    .chatbot-tools { display: flex; align-items: center; gap: .6rem; flex: 1 1 18rem; justify-content: flex-end; }
    .chatbot-tools .fi-input-wrp { flex: 1 1 14rem; max-width: 24rem; min-width: 0; border-radius: 999px; box-shadow: 0 1px 3px rgb(0 0 0 / .18); }
    .chatbot-clear { display: inline-flex; align-items: center; gap: .4rem; flex: none; min-height: 2.75rem; padding: .55rem 1rem; border-radius: 999px; background: transparent; color: var(--on-secondary); font-size: .85rem; font-weight: 700; box-shadow: inset 0 0 0 1.5px currentColor; transition: background .15s; }
    .chatbot-clear:hover { background: color-mix(in srgb, var(--on-secondary) 14%, transparent); }
    .chatbot-log { flex: 1; overflow-y: auto; padding: 1.5rem clamp(1rem, 4vw, 2rem); display: flex; flex-direction: column; gap: 1rem; background: var(--primary-50); }
    .chatbot-log > * { width: 100%; max-width: 56rem; margin-inline: auto; }
    .chatbot-row { display: flex; gap: .65rem; align-items: flex-end; }
    .chatbot-row.eu { justify-content: flex-end; }
    .chatbot-msg { max-width: min(46rem, 85%); padding: .8rem 1.1rem; border-radius: 1.1rem; border-bottom-left-radius: .3rem; background: #fff; color: var(--gray-800); box-shadow: 0 0 0 1px var(--primary-100), 0 1px 2px rgb(15 41 107 / .06); line-height: 1.55; white-space: pre-line; }
    .chatbot-row.eu .chatbot-msg { background: var(--primary-600); color: #fff; box-shadow: none; border-radius: 1.1rem; border-bottom-right-radius: .3rem; }
    .chatbot-msg small { display: block; margin-top: .4rem; font-size: .7rem; color: var(--gray-500); }
    .chatbot-typing { display: flex; gap: .3rem; padding: 1rem 1.1rem; } .chatbot-typing i { width: .5rem; height: .5rem; border-radius: 50%; background: var(--primary-400); animation: chatbot-blink 1.2s infinite; }
    .chatbot-typing i:nth-child(2) { animation-delay: .2s; } .chatbot-typing i:nth-child(3) { animation-delay: .4s; }
    @keyframes chatbot-blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .chatbot-typing i, .li-fab, .chatbot-clear, .chatbot-sug button { animation: none; transition: none; } }
    .chatbot-vazio { margin-block: auto; text-align: center; display: flex; flex-direction: column; align-items: center; gap: .75rem; }
    .chatbot-vazio h2 { font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 800; color: var(--primary-950); margin: 0; text-wrap: balance; }
    .chatbot-vazio p { color: var(--gray-600); margin: 0; max-width: 38rem; }
    .chatbot-sug { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .75rem; margin-top: 1rem; width: 100%; max-width: 44rem; }
    .chatbot-sug button { padding: .9rem 1.1rem; border-radius: .9rem; background: #fff; color: var(--primary-800); font-weight: 600; font-size: .9rem; text-align: left; box-shadow: 0 0 0 1.5px var(--primary-200), 0 1px 2px rgb(15 41 107 / .06); transition: box-shadow .15s, background .15s; }
    .chatbot-sug button:hover { background: var(--primary-50); box-shadow: 0 0 0 2px var(--primary-500), 0 6px 16px rgb(37 99 235 / .15); }
    .chatbot-sug button:focus-visible { outline: 3px solid var(--primary-300); outline-offset: 2px; }
    .chatbot-form { display: flex; gap: .75rem; align-items: center; padding: 1rem clamp(1rem, 4vw, 2rem) 1.25rem; background: #fff; border-top: 1px solid var(--primary-100); }
    .chatbot-form > * { min-width: 0; }
    .chatbot-form input { flex: 1; max-width: 56rem; margin-left: auto; padding: 1rem 1.4rem; border-radius: 999px; border: 1.5px solid var(--primary-200); font-size: 1rem; background: var(--primary-50); color: var(--gray-900); }
    .chatbot-form input:focus { outline: 3px solid var(--primary-300); border-color: var(--primary-500); background: #fff; }
    .chatbot-form button { display: inline-flex; align-items: center; gap: .5rem; margin-right: auto; padding: 1rem 1.6rem; border-radius: 999px; background: var(--primary-600); color: #fff; font-weight: 700; box-shadow: 0 4px 12px rgb(37 99 235 / .3); transition: background .15s; }
    .chatbot-form button:hover { background: var(--primary-700); } .chatbot-form button:disabled { opacity: .6; }
    @media (max-width: 640px) { .chatbot-form button span { display: none; } .chatbot-form button { padding: 1rem; } .chatbot-form input { margin-left: 0; } .chatbot-form button { margin-right: 0; } }

    /* ---------- Planilha (landing pós-login) ---------- */
    .pl, .pl-form { display: flex; flex-direction: column; gap: 1.25rem; }
    .pl-drop { position: relative; display: flex; flex-direction: column; align-items: center; gap: .25rem; margin-top: 1rem; padding: 2rem 1rem; border: 2px dashed var(--primary-300); border-radius: .75rem; background: var(--primary-50); color: var(--primary-900); text-align: center; cursor: pointer; transition: background .15s, border-color .15s; }
    .pl-drop:hover, .pl-drop:focus-within { background: var(--primary-100); border-color: var(--primary-500); }
    .pl-drop span { color: var(--gray-600); font-size: .85rem; }
    .pl-drop-busy { opacity: .7; }
    .pl-file { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .pl-erro { margin: .5rem 0 0; color: #b91c1c; font-size: .875rem; font-weight: 600; }

    /* ---------- Cards de empresas coloridos pelo status ---------- */
    .fi-ta-record.nv-crit { background: #fef2f2; box-shadow: 0 0 0 1px #fca5a5, 0 1px 2px rgb(153 27 27 / .08); }
    .fi-ta-record.nv-alto { background: #fff7ed; box-shadow: 0 0 0 1px #fdba74, 0 1px 2px rgb(154 52 18 / .08); }
    .fi-ta-record.nv-med { background: var(--primary-50); box-shadow: 0 0 0 1px var(--primary-300), 0 1px 2px rgb(15 41 107 / .08); }
    .fi-ta-record.nv-baixo { background: #f0fdf4; box-shadow: 0 0 0 1px #86efac, 0 1px 2px rgb(22 101 52 / .08); }
    .fi-ta-record.nv-canc { background: var(--gray-100); box-shadow: 0 0 0 1px var(--gray-300); opacity: .75; }
    .fi-ta-record[class*='nv-']:hover { filter: brightness(.97); }
    .dark .fi-ta-record.nv-crit { background: rgb(220 38 38 / .14); } .dark .fi-ta-record.nv-alto { background: rgb(249 115 22 / .14); }
    .dark .fi-ta-record.nv-baixo { background: rgb(34 197 94 / .12); } .dark .fi-ta-record.nv-med { background: rgb(37 99 235 / .16); } .dark .fi-ta-record.nv-canc { background: rgb(148 163 184 / .12); }

    /* ---------- Painel responsivo ---------- */
    .fi-wi-stats-overview-stat-value { font-size: clamp(1.35rem, 1rem + 1.4vw, 2rem); overflow-wrap: anywhere; line-height: 1.15; }
    .fi-wi-stats-overview-stat-label, .fi-wi-stats-overview-stat-description { overflow-wrap: anywhere; }
    .li-h2 { font-size: 1.15rem; font-weight: 700; color: var(--primary-900); margin: 0; }
</style>
