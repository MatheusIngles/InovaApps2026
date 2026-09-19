<div class="chatbot">
    <header class="chatbot-head">
        <div class="chatbot-id">
            <span class="chatbot-avatar" aria-hidden="true">IA</span>
            <div>
                <strong>Assistente de carteira</strong>
                <small>{{ $foco ? 'Especialista em '.$foco->nome : 'Visão geral da carteira' }}</small>
            </div>
        </div>
        <div class="chatbot-tools">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="codigo" aria-label="Empresa em foco">
                    <option value="">Carteira inteira</option>
                    @foreach ($empresas as $e)
                        <option value="{{ $e->codigo }}">{{ $e->codigo }} · {{ $e->nome }}{{ $e->cancelada() ? ' (cancelada)' : '' }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <button type="button" class="chatbot-clear" wire:click="limpar" title="Nova conversa">Nova conversa</button>
        </div>
    </header>

    <div class="chatbot-log" aria-live="polite" x-data x-effect="$wire.mensagens.length; $nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($mensagens as $m)
            <div class="chatbot-row {{ $m['eu'] ? 'eu' : '' }}">
                @unless ($m['eu'])<span class="chatbot-avatar sm" aria-hidden="true">IA</span>@endunless
                <div class="chatbot-msg">
                    {{ $m['texto'] }}
                    @isset($m['fonte'])<small>{{ $m['fonte'] }}</small>@endisset
                </div>
            </div>
        @empty
            <div class="chatbot-vazio">
                <span class="chatbot-avatar lg" aria-hidden="true">IA</span>
                <h2>{{ $foco ? 'Pergunte sobre '.$foco->nome : 'Como posso ajudar com a carteira?' }}</h2>
                <p>{{ $foco ? 'Tenho o contexto completo desta empresa: contrato, sinais de risco, NPS, histórico e cancelados parecidos.' : 'Escolha uma empresa acima ou cite o código dela (ex.: C012) para uma resposta específica.' }}</p>
                <div class="chatbot-sug">
                    @foreach ($sugestoes as $s)
                        <button type="button" wire:click="enviar(@js($s))">{{ $s }}</button>
                    @endforeach
                </div>
            </div>
        @endforelse

        <div class="chatbot-row" wire:loading wire:target="enviar">
            <span class="chatbot-avatar sm" aria-hidden="true">IA</span>
            <div class="chatbot-msg chatbot-typing"><i></i><i></i><i></i></div>
        </div>
    </div>

    <form class="chatbot-form" wire:submit="enviar">
        <input type="text" wire:model="pergunta" maxlength="500" autocomplete="off" autofocus
               placeholder="{{ $foco ? 'Pergunte algo sobre '.$foco->nome.'…' : 'Pergunte sobre a carteira…' }}" aria-label="Sua pergunta">
        <button type="submit" wire:loading.attr="disabled" wire:target="enviar">Enviar</button>
    </form>
</div>
