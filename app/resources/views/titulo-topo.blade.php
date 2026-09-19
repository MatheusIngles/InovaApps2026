{{-- Celular: o título da página sobe para a barra do topo (o h1 continua no DOM, escondido só visualmente). --}}
<script>
    (() => {
        const montar = () => {
            const barra = document.querySelector('.fi-topbar');
            const titulo = document.querySelector('h1.fi-header-heading, h1.chatbot-title');
            if (!barra || !titulo || barra.querySelector('.topbar-title')) return;
            const span = document.createElement('span');
            span.className = 'topbar-title';
            span.setAttribute('aria-hidden', 'true');
            span.textContent = titulo.textContent.trim();
            barra.appendChild(span);
            document.body.classList.add('tem-titulo-topo');
        };
        montar();
        document.addEventListener('livewire:navigated', montar);
    })();
</script>
