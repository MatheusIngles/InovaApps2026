{{-- Balança o sino quando chega notificação nova (o contador do badge muda). --}}
<script>
    (() => {
        let ultimo = null;
        const ler = () => document.querySelector('.fi-icon-btn-badge-ctn')?.textContent.trim() ?? '';
        const tocar = () => {
            const btn = document.querySelector('.fi-icon-btn:has(.fi-icon-btn-badge-ctn)') || document.querySelector('.fi-topbar-database-notifications-btn');
            if (!btn) return;
            btn.classList.remove('sino-toca'); void btn.offsetWidth; btn.classList.add('sino-toca');
            setTimeout(() => btn.classList.remove('sino-toca'), 800);
        };
        new MutationObserver(() => {
            const atual = ler();
            if (ultimo !== null && atual !== ultimo && atual !== '') tocar();
            ultimo = atual;
        }).observe(document.body, { childList: true, subtree: true, characterData: true });
        ultimo = ler();
    })();
</script>
