<div class="chatbot" x-data="{
            ouvindo: false, erro: '', rec: null, pendente: null,
            suportado: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
            alternar() {
                if (this.ouvindo) { this.rec.stop(); return; }
                const Reconhecimento = window.SpeechRecognition || window.webkitSpeechRecognition;
                const campo = this.$refs.campo, base = campo.value.trim() ? campo.value.trim() + ' ' : '';
                this.rec = new Reconhecimento();
                this.rec.lang = 'pt-BR';
                this.rec.interimResults = true;
                this.rec.onresult = (e) => {
                    campo.value = (base + [...e.results].map(r => r[0].transcript).join('')).slice(0, 500);
                    campo.dispatchEvent(new Event('input')); // avisa o Livewire
                };
                this.rec.onend = () => { this.ouvindo = false; campo.focus(); };
                this.rec.onerror = (e) => { this.erro = e.error === 'not-allowed' ? 'Permita o uso do microfone no navegador para falar com o assistente.' : 'Não consegui ouvir. Tente de novo.'; };
                this.erro = '';
                this.rec.start();
                this.ouvindo = true;
            },
            /** Mostra a pergunta no chat e o indicador de carregamento na hora, sem esperar a resposta do servidor. */
            async enviar(texto) {
                const campo = this.$refs.campo;
                texto = (texto ?? campo.value).trim().slice(0, 500);
                if (!texto || this.pendente) return;
                if (this.ouvindo) this.rec.stop();
                this.pendente = texto;
                campo.value = '';
                campo.dispatchEvent(new Event('input'));
                try { await $wire.enviar(texto); } finally { this.pendente = null; }
            },
        }">
    <header class="chatbot-head">
        <div class="chatbot-id">
            <div>
                <h1 class="chatbot-title">Assistente de carteira</h1>
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
        </div>
    </header>

    <div class="chatbot-log" role="log" aria-live="polite" aria-label="Conversa" x-effect="pendente; $wire.mensagens.length; $nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($mensagens as $m)
            <div class="chatbot-row {{ $m['eu'] ? 'eu' : '' }}">
                <div class="chatbot-msg">{{ $m['texto'] }}@isset($m['fonte'])<small>{{ $m['fonte'] }}</small>@endisset</div>
            </div>
        @empty
            <div class="chatbot-vazio" x-show="!pendente">
                <h2>{{ $foco ? 'Pergunte sobre '.$foco->nome : 'Como posso ajudar com a carteira?' }}</h2>
                <p>{{ $foco ? 'Tenho o contexto completo desta empresa: contrato, sinais de risco, NPS, histórico e cancelados parecidos.' : 'Escolha uma empresa acima ou cite o código dela (ex.: C012) para uma resposta específica.' }}</p>
                <div class="chatbot-sug">
                    @foreach ($sugestoes as $s)
                        <button type="button" x-on:click="enviar(@js($s))" :disabled="pendente">{{ $s }}</button>
                    @endforeach
                </div>
            </div>
        @endforelse

        <div class="chatbot-row eu" x-show="pendente" x-cloak><div class="chatbot-msg" x-text="pendente"></div></div>
        <div class="chatbot-row" x-show="pendente" x-cloak role="status">
            <div class="chatbot-msg chatbot-typing"><i></i><i></i><i></i><span>Analisando a carteira…</span></div>
        </div>
    </div>

    <form class="chatbot-form" x-on:submit.prevent="enviar()">
        <div class="chatbot-composer" :class="{ 'ouvindo': ouvindo }">
            <input type="text" x-ref="campo" wire:model="pergunta" maxlength="500" autocomplete="off" autofocus
                   :placeholder="ouvindo ? 'Ouvindo… pode falar' : @js($foco ? 'Pergunte algo sobre '.$foco->nome.'…' : 'Pergunte sobre a carteira…')" aria-label="Sua pergunta">
            <button type="button" class="chatbot-mic" x-show="suportado" x-cloak x-on:click="alternar()" :aria-pressed="ouvindo"
                    :aria-label="ouvindo ? 'Parar de ouvir' : 'Falar a pergunta'" :title="ouvindo ? 'Parar de ouvir' : 'Falar a pergunta'">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
            </button>
            <button type="submit" class="chatbot-send" :disabled="pendente" aria-label="Enviar pergunta" title="Enviar">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            </button>
        </div>
        <p class="chatbot-aviso" role="status" x-text="erro" x-show="erro" x-cloak></p>
    </form>
</div>
