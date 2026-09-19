<div class="chat">
    <style>
        .chat { display: flex; flex-direction: column; gap: .75rem; }
        .chat-log { display: flex; flex-direction: column; gap: .5rem; min-height: 16rem; max-height: 28rem; overflow-y: auto; padding: 1rem; border-radius: .75rem; background: var(--gray-50); border: 1px solid var(--gray-200); }
        .chat-msg { max-width: 85%; padding: .6rem .9rem; border-radius: 1rem; font-size: .875rem; line-height: 1.45; white-space: pre-line; background: #fff; color: var(--gray-800); border: 1px solid var(--gray-200); border-top-left-radius: .25rem; }
        .chat-msg.eu { align-self: flex-end; background: var(--primary-600); color: #fff; border-color: var(--primary-600); border-top-left-radius: 1rem; border-top-right-radius: .25rem; }
        .chat-sug { display: flex; flex-wrap: wrap; gap: .5rem; }
    </style>

    @if ($seletor)
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="codigo" aria-label="Focar em uma empresa">
                <option value="">Carteira inteira</option>
                @foreach ($empresas as $e)
                    <option value="{{ $e->codigo }}">{{ $e->codigo }} · {{ $e->nome }}{{ $e->status === 'Cancelado' ? ' (cancelada)' : '' }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    @endif

    <div class="chat-log" aria-live="polite" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$wire.mensagens.length; $nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @foreach ($mensagens as $m)
            <div class="chat-msg {{ $m['eu'] ? 'eu' : '' }}">{{ $m['texto'] }}</div>
        @endforeach
        <div wire:loading wire:target="enviar" class="chat-msg">Analisando…</div>
    </div>

    <div class="chat-sug">
        @foreach ($sugestoes as $s)
            <x-filament::button color="gray" size="xs" outlined wire:click="enviar(@js($s))">{{ $s }}</x-filament::button>
        @endforeach
    </div>

    <form wire:submit="enviar" style="display: flex; gap: .5rem;">
        <div style="flex: 1;">
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="pergunta" maxlength="300" autocomplete="off" placeholder="Digite sua pergunta…" aria-label="Sua pergunta" />
            </x-filament::input.wrapper>
        </div>
        <x-filament::button type="submit">Enviar</x-filament::button>
    </form>
</div>
