<div class="ui-actions" x-data="{ suportado: 'EyeDropper' in window }">
    <p class="ui-muted" x-show="suportado">Ou pegue a cor direto do logo (ou de qualquer ponto da tela):</p>
    <template x-if="suportado">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <x-filament::button color="gray" size="sm" type="button"
                x-on:click="new EyeDropper().open().then(r => { $wire.set('data.tema.primary', r.sRGBHex); window.previewTema([r.sRGBHex, $wire.data.tema.secondary]) }).catch(() => {})">Cor primária do logo</x-filament::button>
            <x-filament::button color="gray" size="sm" type="button"
                x-on:click="new EyeDropper().open().then(r => { $wire.set('data.tema.secondary', r.sRGBHex); window.previewTema([$wire.data.tema.primary, r.sRGBHex]) }).catch(() => {})">Cor secundária do logo</x-filament::button>
        </div>
    </template>
</div>
