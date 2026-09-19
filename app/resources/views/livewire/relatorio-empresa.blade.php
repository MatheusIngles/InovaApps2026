<div>
    <button type="button" class="ui-btn" wire:click="abrir">Gerar relatório</button>

    <x-filament::modal id="relatorio-empresa" width="lg" heading="Gerar relatório de pontos críticos">
        <form wire:submit="gerar" class="ui-relatorio-form">
            <p class="ui-muted">Escolha os pontos críticos. A análise será gerada em segundo plano, e você receberá o PDF nas notificações do painel.</p>

            {{ $this->form }}

            <div class="ui-actions">
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="gerar">
                    <span wire:loading.remove wire:target="gerar">Solicitar relatório</span>
                    <span wire:loading wire:target="gerar">Enviando…</span>
                </x-filament::button>
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'relatorio-empresa' })">Cancelar</x-filament::button>
            </div>
        </form>
    </x-filament::modal>
</div>
