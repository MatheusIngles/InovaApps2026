<div class="pl">
    <section class="li-card li-pad">
        <h2>Enviar planilha</h2>
        <p class="li-muted">Cada linha é um cliente em um mês. Depois do envio, escolha a coluna de cada campo. Meses já importados só têm os valores atualizados.</p>

        <label class="pl-drop" wire:loading.class="pl-drop-busy" wire:target="arquivo">
            <input type="file" wire:model="arquivo" accept=".xlsx,.csv,.txt" class="pl-file">
            <strong>{{ $cabecalhos ? 'Enviar outra planilha' : 'Escolher arquivo' }}</strong>
            <span wire:loading.remove wire:target="arquivo">.xlsx ou .csv, até 20 MB</span>
            <span wire:loading wire:target="arquivo">Lendo a planilha…</span>
        </label>
        @error('arquivo')<p class="pl-erro" role="alert">{{ $message }}</p>@enderror
    </section>

    @if ($cabecalhos)
        <section class="li-card li-pad">
            <h2>Mapeamento de colunas</h2>
            <p class="li-muted">Só o código do cliente é obrigatório. Sem dado de uso da plataforma, o cliente não é penalizado.</p>
            <form wire:submit="importar" class="pl-form">
                {{ $this->form }}

                <div class="li-table-wrap">
                    <p class="li-muted">Prévia das primeiras linhas:</p>
                    <table class="li-table">
                        <thead><tr>@foreach ($cabecalhos as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($previa as $linha)
                                <tr>@foreach ($cabecalhos as $h)<td>{{ $linha[$h] ?? '' }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="li-actions">
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="importar">Importar e recalcular risco</x-filament::button>
                    <x-filament::button color="gray" wire:click="descartar">Cancelar</x-filament::button>
                </div>
            </form>
        </section>
    @endif

    @if ($resultado)
        <section class="li-card li-pad" role="status">
            <h2>Importação concluída</h2>
            <p class="li-about">{{ $resultado['clientes'] }} clientes · {{ $resultado['meses'] }} meses de métricas · {{ $resultado['nps'] }} pesquisas de NPS{{ $resultado['ignoradas'] ? ' · '.$resultado['ignoradas'].' linhas ignoradas (código ou mês inválido)' : '' }}.</p>
        </section>
    @endif
</div>
