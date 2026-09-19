{{-- Leitura em voz alta: com o botão ligado, o que estiver sob o mouse ou o foco do teclado é falado (Web Speech API, pt-BR). --}}
<button type="button" class="ac-voz" id="ac-voz" aria-pressed="false" hidden>
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>
    <span class="ac-voz-texto">Leitura em voz alta</span>
</button>
<script>
    (() => {
        const botao = document.getElementById('ac-voz');
        if (!botao || !('speechSynthesis' in window)) return;
        botao.hidden = false;
        const fim = document.querySelector('.fi-topbar-end'); // nas telas com barra, o botão mora nela
        if (fim) { fim.prepend(botao); botao.classList.add('no-topo'); }

        const guardar = (v) => { try { localStorage.setItem('leitura-voz', v ? '1' : '0'); } catch (e) {} };
        const lido = () => { try { return localStorage.getItem('leitura-voz') === '1'; } catch (e) { return false; } };
        let ligado = lido(), timer = null, ultimo = '';

        const falar = (texto) => {
            speechSynthesis.cancel();
            const fala = new SpeechSynthesisUtterance(texto);
            fala.lang = 'pt-BR';
            fala.voice = speechSynthesis.getVoices().find((v) => v.lang === 'pt-BR') || null;
            speechSynthesis.speak(fala);
        };
        const atualizar = () => {
            botao.setAttribute('aria-pressed', ligado);
            botao.setAttribute('aria-label', ligado ? 'Desativar leitura em voz alta' : 'Ativar leitura em voz alta');
            botao.classList.toggle('ligado', ligado);
        };

        const tipo = (el) => {
            const tag = el.tagName, papel = el.getAttribute('role');
            if (tag === 'A' || papel === 'link') return 'link';
            if (tag === 'BUTTON' || papel === 'button') return 'botão';
            if (tag === 'SELECT') return 'lista de opções';
            if (tag === 'TEXTAREA' || (tag === 'INPUT' && !['checkbox', 'radio', 'submit', 'button', 'file'].includes(el.type))) return 'campo de texto';
            if (tag === 'INPUT' && el.type === 'checkbox') return 'caixa de seleção';
            if (tag === 'INPUT' && el.type === 'file') return 'envio de arquivo';
            if (/^H[1-6]$/.test(tag)) return 'título';
            if (tag === 'IMG') return 'imagem';
            return '';
        };
        const nome = (el) => {
            const ref = el.getAttribute('aria-labelledby');
            const rotulo = ref ? document.getElementById(ref)?.textContent : el.labels?.[0]?.textContent;
            const bruto = el.getAttribute('aria-label') || rotulo || el.getAttribute('title') || el.getAttribute('alt') || el.getAttribute('placeholder')
                || (el.tagName === 'SELECT' ? el.selectedOptions[0]?.textContent : '') || el.innerText || el.textContent || '';
            return bruto.replace(/\s+/g, ' ').trim().slice(0, 160);
        };
        const alvo = (el) => el.closest('a, button, input, select, textarea, h1, h2, h3, h4, img, summary, [role=button], [role=link], [aria-label], .chatbot-msg, .ui-card, .fi-section-header, .fi-wi-stats-overview-stat, .fi-ta-record');

        const descrever = (evento) => {
            if (!ligado || !(evento.target instanceof Element) || evento.target.closest('[vw]')) return;
            const el = alvo(evento.target);
            if (!el) return;
            const texto = [nome(el), tipo(el)].filter(Boolean).join(', ');
            if (!texto || texto === ultimo && evento.type === 'mouseover') return;
            clearTimeout(timer);
            timer = setTimeout(() => { ultimo = texto; falar('Em cima de ' + texto); }, evento.type === 'focusin' ? 0 : 250);
        };
        document.addEventListener('mouseover', descrever);
        document.addEventListener('focusin', descrever);
        document.addEventListener('mouseout', () => { ultimo = ''; });

        botao.addEventListener('click', () => {
            ligado = !ligado;
            guardar(ligado);
            atualizar();
            if (ligado) falar('Leitura em voz alta ativada. Passe o mouse ou use o teclado sobre um item para ouvir o que ele é.');
            else { clearTimeout(timer); speechSynthesis.cancel(); }
        });
        atualizar();
    })();
</script>
