{{-- Painel e lista de empresas: os textos explicativos viram uma dica (ícone "i" ao lado do título) em vez de ocupar espaço. --}}
<script>
    (() => {
        const rotas = ['/painel', '/empresas'];
        if (!rotas.includes(location.pathname.replace(/\/$/, ''))) return;
        document.body.classList.add('dicas');

        const dica = document.createElement('div');
        dica.className = 'dica-balao';
        dica.setAttribute('role', 'tooltip');
        dica.hidden = true;
        document.body.appendChild(dica);

        const mostrar = (botao) => {
            dica.textContent = botao.dataset.dica;
            dica.hidden = false;
            const r = botao.getBoundingClientRect(), largura = dica.offsetWidth;
            dica.style.left = Math.max(8, Math.min(r.left + r.width / 2 - largura / 2, innerWidth - largura - 8)) + 'px';
            dica.style.top = (r.bottom + 8 + dica.offsetHeight > innerHeight ? r.top - dica.offsetHeight - 8 : r.bottom + 8) + 'px';
        };
        const esconder = () => { dica.hidden = true; };

        const montar = () => {
            document.querySelectorAll('.fi-header-subheading, .fi-section-header-description, .fi-ta-header-description').forEach((desc) => {
                const titulo = desc.parentElement?.querySelector(':scope > h1, :scope > h2, :scope > .fi-header-heading, :scope > .fi-section-header-heading, :scope > .fi-ta-header-heading');
                const texto = desc.textContent.replace(/\s+/g, ' ').trim();
                if (!titulo || !texto || titulo.querySelector('.dica')) return;
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'dica';
                botao.dataset.dica = texto;
                botao.setAttribute('aria-label', 'Sobre esta seção: ' + texto);
                botao.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>';
                botao.addEventListener('mouseenter', () => mostrar(botao));
                botao.addEventListener('focus', () => mostrar(botao));
                botao.addEventListener('click', () => (dica.hidden ? mostrar(botao) : esconder()));
                botao.addEventListener('mouseleave', esconder);
                botao.addEventListener('blur', esconder);
                titulo.appendChild(botao);
            });
        };
        montar();
        new MutationObserver(montar).observe(document.body, { childList: true, subtree: true }); // widgets carregam depois
        document.addEventListener('keydown', (e) => e.key === 'Escape' && esconder());
        addEventListener('scroll', esconder, { passive: true });
    })();
</script>
