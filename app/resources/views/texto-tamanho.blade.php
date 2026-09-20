{{-- Tamanho do texto (A− / A / A+): escala o rem da página inteira e mora ao lado do seletor de tema (claro/escuro). --}}
<script>
    (() => {
        const NIVEIS = [87.5, 100, 112.5, 125, 137.5]; // % do tamanho base
        const PADRAO = 1;
        const ler = () => { try { const n = parseInt(localStorage.getItem('texto-tamanho'), 10); return n >= 0 && n < NIVEIS.length ? n : PADRAO; } catch (e) { return PADRAO; } };
        const guardar = (n) => { try { localStorage.setItem('texto-tamanho', String(n)); } catch (e) {} };
        let nivel = ler();

        const aplicar = () => {
            document.documentElement.style.fontSize = NIVEIS[nivel] + '%';
            document.querySelectorAll('.fi-tt').forEach((g) => {
                g.querySelector('[data-tt=menos]').disabled = nivel === 0;
                g.querySelector('[data-tt=mais]').disabled = nivel === NIVEIS.length - 1;
                g.querySelector('[data-tt=padrao]').setAttribute('aria-pressed', nivel === PADRAO);
                g.querySelector('.fi-tt-nivel').textContent = NIVEIS[nivel] + '%';
            });
        };
        const mudar = (n) => { nivel = Math.max(0, Math.min(NIVEIS.length - 1, n)); guardar(nivel); aplicar(); };

        const criar = () => {
            const g = document.createElement('div');
            g.className = 'fi-tt';
            g.setAttribute('role', 'group');
            g.setAttribute('aria-label', 'Tamanho do texto');
            g.innerHTML = '<button type="button" data-tt="menos" aria-label="Diminuir o texto" title="Diminuir o texto">A<small>−</small></button>'
                + '<button type="button" data-tt="padrao" aria-label="Tamanho padrão do texto" title="Tamanho padrão"><span class="fi-tt-nivel"></span></button>'
                + '<button type="button" data-tt="mais" aria-label="Aumentar o texto" title="Aumentar o texto">A<small>+</small></button>';
            g.addEventListener('click', (e) => {
                const b = e.target.closest('[data-tt]');
                if (!b) return;
                e.stopPropagation(); // não fecha o menu do usuário
                mudar(b.dataset.tt === 'menos' ? nivel - 1 : b.dataset.tt === 'mais' ? nivel + 1 : PADRAO);
            });
            return g;
        };
        // O menu do usuário é renderizado sob demanda: observa o DOM e encaixa o controle ao lado do seletor de tema.
        const encaixar = () => {
            document.querySelectorAll('.fi-theme-switcher').forEach((s) => {
                if (s.nextElementSibling?.classList.contains('fi-tt')) return;
                s.after(criar());
                aplicar();
            });
        };

        aplicar(); // aplica já no <head>, sem piscar o tamanho antigo
        document.addEventListener('DOMContentLoaded', () => {
            encaixar();
            new MutationObserver(encaixar).observe(document.body, { childList: true, subtree: true });
        });
    })();
</script>
