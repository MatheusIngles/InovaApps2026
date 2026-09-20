{{-- Navegação por comando de voz (Web Speech API, pt-BR). O ícone mostra se ela está desligada, ouvindo ou bloqueada; o microfone só é usado depois que o usuário permite. --}}
@auth
    @php
        $destinosVoz = [
            'painel' => \App\Filament\Pages\Painel::getUrl(),
            'assistente' => \App\Filament\Pages\Assistente::getUrl(),
            'configuracoes' => \App\Filament\Pages\Configuracoes::getUrl(),
            'planilha' => \App\Filament\Pages\Planilha::getUrl(),
            'clientes' => \App\Filament\Resources\Empresas\EmpresaResource::getUrl(),
        ];
    @endphp
    <button type="button" class="vn-voz" id="vn-voz" aria-pressed="false" hidden>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/><path class="vn-corte" d="M3 3l18 18"/>
        </svg>
        <span class="vn-ponto" aria-hidden="true"></span>
        <span class="vn-texto">Comandos de voz: desligado</span>
    </button>
    <dialog id="vn-dialogo" class="vn-dialogo" aria-labelledby="vn-titulo">
        <form method="dialog">
            <h2 id="vn-titulo">Permitir o uso do microfone?</h2>
            <p>Para navegar por voz, o navegador precisa ouvir você enquanto este recurso estiver ligado. O áudio é processado pelo serviço de reconhecimento de voz do navegador e nada é gravado pelo Seer.</p>
            <p class="vn-exemplos">Exemplos: “abrir painel”, “assistente”, “configurações”, “notificações”.</p>
            <div class="vn-acoes">
                <button value="nao" class="vn-nao">Agora não</button>
                <button value="sim" class="vn-sim" autofocus>Permitir microfone</button>
            </div>
        </form>
    </dialog>
    <dialog id="vn-ajuda" class="vn-dialogo" aria-labelledby="vn-ajuda-titulo">
        <form method="dialog">
            <h2 id="vn-ajuda-titulo">Comandos de voz</h2>
            <dl class="vn-lista">
                <dt>Telas</dt><dd>“painel de controle”, “assistente”, “configurações”, “clientes”, “planilha”</dd>
                <dt>Abas da tela</dt><dd>“aba histórico mensal”, “por segmento”, “prioridades”, “próxima aba”, “aba anterior”</dd>
                <dt>Segmento</dt><dd>“segmento” + o nome, na aba Por segmento</dd>
                <dt>Notificações</dt><dd>“notificações” abre; “fechar notificações” ou “fechar” fecha</dd>
                <dt>Navegação</dt><dd>“voltar”, “rolar para baixo”, “rolar para cima”</dd>
                <dt>Ajuda</dt><dd>“ajuda” ou “quais comandos”</dd>
            </dl>
            <div class="vn-acoes"><button value="ok" class="vn-sim" autofocus>Fechar</button></div>
        </form>
    </dialog>
    <div class="vn-aviso" id="vn-aviso" role="status" aria-live="polite" hidden></div>
    <script>
        (() => {
            const botao = document.getElementById('vn-voz'), dialogo = document.getElementById('vn-dialogo'), aviso = document.getElementById('vn-aviso');
            const Reconhecimento = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!botao || !Reconhecimento) return;
            botao.hidden = false;
            const fim = document.querySelector('.fi-topbar-end');
            if (fim) { fim.prepend(botao); botao.classList.add('no-topo'); }

            const DESTINOS = @json($destinosVoz);
            const guardar = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
            const ler = (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } };

            let ligado = false, rec = null, pausado = false, negado = false, timerAviso = null;

            const mostrar = (texto, ms = 4000) => {
                aviso.textContent = texto; aviso.hidden = false;
                clearTimeout(timerAviso); timerAviso = setTimeout(() => { aviso.hidden = true; }, ms);
            };
            const atualizar = () => {
                const ouvindo = ligado && !pausado;
                botao.classList.toggle('ligado', ouvindo);
                botao.classList.toggle('negado', negado);
                botao.setAttribute('aria-pressed', ouvindo);
                const estado = negado ? 'bloqueado (permita o microfone no navegador)' : (ouvindo ? 'ligado, ouvindo' : (ligado ? 'em pausa' : 'desligado'));
                botao.querySelector('.vn-texto').textContent = 'Comandos de voz: ' + estado;
                botao.setAttribute('aria-label', 'Navegação por voz: ' + estado + (ligado ? '. Clique para desligar.' : '. Clique para ligar.'));
                botao.title = botao.getAttribute('aria-label');
            };

            const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
            const ir = (url, msg) => { mostrar(msg); if (location.href.split('#')[0] !== url) setTimeout(() => { location.href = url; }, 350); };
            const sino = () => document.querySelector('.fi-topbar-database-notifications-btn, .fi-icon-btn:has(.fi-icon-btn-badge-ctn), .fi-topbar button[aria-label*="otifica" i]');

            const visivel = (el) => el.getClientRects().length > 0;
            const clicar = (el) => { el.click(); el.focus?.(); };
            const abas = () => [...document.querySelectorAll('[role=tab]')].filter(visivel);
            const semPrefixo = (t) => { let r = t, ant; do { ant = r; r = r.replace(/^(ir para|ir pra|abrir|mostrar|ver|acessar|va para|vai para|na|a|aba|guia)\s+/, ''); } while (r !== ant); return r; };
            // aba pelo nome que aparece na tela (vale para qualquer painel, ex.: Visão geral, Por segmento, Prioridades, Histórico mensal)
            const abaPorNome = (t) => {
                const q = semPrefixo(t);
                if (q.length < 3) return null;
                let melhor = null, nota = 0;
                for (const a of abas()) {
                    const l = norm(a.textContent);
                    const n = l === q ? 3 : (q.includes(l) && l.length >= 4 ? 2 : (l.includes(q) && q.length >= 4 ? 1 : 0));
                    if (n > nota) { melhor = a; nota = n; }
                }
                return melhor;
            };
            const abaVizinha = (passo) => {
                const lista = abas(), i = lista.findIndex((a) => a.getAttribute('aria-selected') === 'true');
                if (!lista.length) return null;
                return lista[(Math.max(i, 0) + passo + lista.length) % lista.length];
            };
            const fechar = () => {
                if (dialogo.open) { dialogo.close('nao'); return true; }
                const ajuda = document.getElementById('vn-ajuda');
                if (ajuda.open) { ajuda.close(); return true; }
                const x = [...document.querySelectorAll('.fi-modal-close-btn')].find(visivel);
                if (x) { x.click(); return true; }
                if ([...document.querySelectorAll('.fi-modal-window')].some(visivel)) { document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); return true; }
                return false;
            };

            const interpretar = (bruto) => {
                const t = norm(bruto);
                if (!t) return;
                if (/\b(ajuda|comandos)\b/.test(t)) { document.getElementById('vn-ajuda').showModal(); return; }
                if (/\b(fechar|fecha|feche|esconder)\b/.test(t)) { mostrar(fechar() ? 'Fechado' : 'Não há nada aberto para fechar'); return; }
                if (/\b(voltar|volta)\b/.test(t)) { mostrar('Voltando'); history.back(); return; }
                if (/\brolar?\b.*\b(baixo|descer)\b|\b(descer)\b/.test(t)) { scrollBy({ top: innerHeight * .8, behavior: 'smooth' }); return; }
                if (/\brolar?\b.*\b(cima|subir)\b|\b(subir|topo)\b/.test(t)) { scrollBy({ top: -innerHeight * .8, behavior: 'smooth' }); return; }
                if (/\b(proxima|seguinte)\b.*\baba\b|\baba\b.*\b(proxima|seguinte)\b/.test(t)) { const a = abaVizinha(1); return a ? (clicar(a), mostrar('Aba ' + a.textContent.trim())) : mostrar('Esta tela não tem abas'); }
                if (/\b(anterior)\b.*\baba\b|\baba\b.*\banterior\b/.test(t)) { const a = abaVizinha(-1); return a ? (clicar(a), mostrar('Aba ' + a.textContent.trim())) : mostrar('Esta tela não tem abas'); }
                if (/\bsegmento\b/.test(t)) {
                    const alvo = semPrefixo(t.replace(/\bsegmento\b/, '').trim());
                    const b = alvo && [...document.querySelectorAll('.ev-seg button')].find((x) => norm(x.textContent).startsWith(alvo) || norm(x.textContent).includes(alvo));
                    if (b) { clicar(b); return mostrar('Segmento ' + b.textContent.trim().split(/\s+/)[0]); }
                }
                const aba = abaPorNome(t);
                if (aba) { clicar(aba); return mostrar('Aba ' + aba.textContent.trim()); }
                if (/\baba\b/.test(t)) return mostrar('Não encontrei essa aba nesta tela');
                if (/\b(notificac\w*|avisos?)\b/.test(t)) {
                    const b = sino();
                    if (b) { b.click(); mostrar('Abrindo notificações'); } else mostrar('Não encontrei o botão de notificações');
                    return;
                }
                if (/\b(configurac\w*|ajustes)\b/.test(t)) return ir(DESTINOS.configuracoes, 'Abrindo configurações');
                if (/\b(assistente|chat)\b/.test(t)) return ir(DESTINOS.assistente, 'Abrindo o assistente');
                if (/\b(planilha|importar)\b/.test(t)) return ir(DESTINOS.planilha, 'Abrindo a planilha');
                if (/\b(painel|dashboard|visao geral|inicio)\b/.test(t)) return ir(DESTINOS.painel, 'Abrindo o painel de controle');
                if (/\b(clientes|empresas)\b/.test(t)) return ir(DESTINOS.clientes, 'Abrindo a lista de clientes');
                mostrar('Não entendi “' + bruto.trim() + '”');
            };

            const iniciar = () => {
                if (rec) return;
                rec = new Reconhecimento();
                rec.lang = 'pt-BR'; rec.continuous = true; rec.interimResults = false;
                rec.onresult = (e) => { const r = e.results[e.results.length - 1]; if (r.isFinal) interpretar(r[0].transcript); };
                rec.onerror = (e) => {
                    if (e.error === 'not-allowed' || e.error === 'service-not-allowed') { negado = true; ligado = false; guardar('voz-nav', '0'); mostrar('Microfone bloqueado. Permita o acesso nas configurações do navegador para usar comandos de voz.', 7000); atualizar(); }
                };
                rec.onend = () => { rec = null; if (ligado && !pausado && !negado) setTimeout(iniciar, 250); }; // o navegador encerra a escuta sozinho de tempos em tempos
                try { rec.start(); } catch (e) { rec = null; }
            };
            const parar = () => { const r = rec; rec = null; if (r) { r.onend = null; try { r.stop(); } catch (e) {} } };

            const ligar = () => { negado = false; ligado = true; pausado = false; guardar('voz-nav', '1'); iniciar(); atualizar(); mostrar('Comandos de voz ligados. Diga, por exemplo: “abrir painel”.'); };
            const desligar = () => { ligado = false; parar(); guardar('voz-nav', '0'); atualizar(); mostrar('Comandos de voz desligados'); };

            const pedirPermissao = async () => {
                // 1) pergunta ao usuário; 2) só então dispara o pedido do navegador
                if (ler('voz-nav-consentimento') !== '1') {
                    const decisao = await new Promise((resolver) => {
                        dialogo.addEventListener('close', () => resolver(dialogo.returnValue === 'sim'), { once: true });
                        dialogo.showModal();
                    });
                    if (!decisao) { mostrar('Tudo bem, os comandos de voz continuam desligados.'); return; }
                    guardar('voz-nav-consentimento', '1');
                }
                try {
                    const fluxo = await navigator.mediaDevices.getUserMedia({ audio: true });
                    fluxo.getTracks().forEach((f) => f.stop());
                    ligar();
                } catch (e) {
                    negado = true; atualizar();
                    mostrar('O navegador bloqueou o microfone. Permita o acesso pelo cadeado da barra de endereço.', 7000);
                }
            };

            botao.addEventListener('click', () => { if (ligado) desligar(); else pedirPermissao(); });
            // o microfone do chat usa o mesmo reconhecimento de voz: pausa a navegação enquanto ele estiver em uso
            window.addEventListener('voz-pausar', () => { pausado = true; parar(); atualizar(); });
            window.addEventListener('voz-retomar', () => { pausado = false; if (ligado) iniciar(); atualizar(); });

            atualizar();
            // manteve ligado na página anterior e o navegador já tem a permissão: volta a ouvir sem perguntar de novo
            if (ler('voz-nav') === '1' && ler('voz-nav-consentimento') === '1' && navigator.permissions?.query) {
                navigator.permissions.query({ name: 'microphone' }).then((p) => { if (p.state === 'granted') { ligado = true; iniciar(); atualizar(); } else { guardar('voz-nav', '0'); } }).catch(() => {});
            }
        })();
    </script>
@endauth
