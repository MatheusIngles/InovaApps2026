<x-filament-panels::page>
    <div class="pl-form">
        {{ $this->form }}
    </div>

    <script>
        // Prévia das cores do logo: sobrescreve as variáveis do tema até salvar (ao recarregar volta ao que está salvo).
        window.previewTema = ([primaria, secundaria]) => {
            const r = document.documentElement.style;
            const mistura = (cor, com, pct) => `color-mix(in srgb, ${cor} ${pct}%, ${com})`;
            const tons = {50: [95, 'white'], 100: [88, 'white'], 200: [76, 'white'], 300: [60, 'white'], 400: [80, 'white'], 500: [100, 'white'], 600: [88, 'black'], 700: [74, 'black'], 800: [60, 'black'], 900: [46, 'black'], 950: [30, 'black']};
            const claros = {50: 8, 100: 16, 200: 32, 300: 52, 400: 76};
            Object.entries(tons).forEach(([n, [pct, com]]) => r.setProperty(`--primary-${n}`, claros[n] ? mistura(primaria, 'white', claros[n]) : mistura(primaria, com, pct)));
            r.setProperty('--tenant-secondary', secundaria);
        };
        // Cor de destaque do logo: mais frequente entre os pixels coloridos (ignora transparente, branco, preto e cinza).
        window.corDoLogo = ($wire, url) => {
            const img = new Image();
            img.onload = () => {
                const c = document.createElement('canvas');
                c.width = c.height = 48;
                const ctx = c.getContext('2d', {willReadFrequently: true});
                ctx.drawImage(img, 0, 0, 48, 48);
                const px = ctx.getImageData(0, 0, 48, 48).data, grupos = {};
                for (let i = 0; i < px.length; i += 4) {
                    const [r, g, b, a] = [px[i], px[i + 1], px[i + 2], px[i + 3]];
                    const max = Math.max(r, g, b), min = Math.min(r, g, b);
                    if (a < 200 || max < 40 || min > 225 || max - min < 40) continue;
                    const k = (r >> 5) + '.' + (g >> 5) + '.' + (b >> 5);
                    const s = grupos[k] ||= [0, 0, 0, 0];
                    s[0]++; s[1] += r; s[2] += g; s[3] += b;
                }
                const top = Object.values(grupos).sort((x, y) => y[0] - x[0])[0];
                if (!top) return;
                let rgb = [1, 2, 3].map(i => top[i] / top[0]);
                const lum = v => v.map(x => (x /= 255) <= .03928 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4).reduce((s, x, i) => s + x * [.2126, .7152, .0722][i], 0);
                while (1.05 / (lum(rgb) + .05) < 3) rgb = rgb.map(x => x * .9); // texto branco dos botões precisa de contraste
                const hex = v => '#' + v.map(x => Math.round(x).toString(16).padStart(2, '0')).join('');
                const primaria = hex(rgb), secundaria = hex(rgb.map(x => x * .8));
                $wire.set('data.tema.primary', primaria);
                $wire.set('data.tema.secondary', secundaria);
                window.previewTema([primaria, secundaria]);
            };
            img.src = url;
        };
    </script>
</x-filament-panels::page>
