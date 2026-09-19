<x-filament-panels::page>
    <form wire:submit="salvar" class="pl-form">
        {{ $this->form }}

        <div class="li-actions">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="salvar">Salvar configuração</x-filament::button>
        </div>
    </form>

    <section class="pl" aria-labelledby="novos-meses">
        <div>
            <h2 id="novos-meses" class="li-h2">Acrescentar novos meses</h2>
            <p class="li-muted">Envie a planilha com os meses novos para continuar populando os dados. O risco de todos os clientes é recalculado.</p>
        </div>
        <livewire:importar-planilha />
    </section>
</x-filament-panels::page>
