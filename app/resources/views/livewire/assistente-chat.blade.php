<div class="chatbot" x-data="{
            ouvindo: false, erro: '', rec: null, pendente: null,
            prontas: @js($prontas), texto: '', ativo: -1, fechado: false, focado: false,
            /** Sem acento e em minúsculas, para casar 'atencao' com 'atenção'. */
            norm(s) { return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); },
            /** Campo vazio e em foco: todas as perguntas prontas. Digitando: as que contêm todas as palavras digitadas. */
            get sugeridas() {
                if (!this.focado || this.fechado || this.pendente) return [];
                const q = this.norm(this.texto).trim();
                if (!q) return this.prontas;
                const palavras = q.split(/\s+/);
                return this.prontas.filter(p => { const n = this.norm(p); return n !== q && palavras.every(w => n.includes(w)); }).slice(0, 8);
            },
            mover(passo) {
                const n = this.sugeridas.length;
                if (!n) return;
                this.ativo = this.ativo < 0 ? (passo > 0 ? 0 : n - 1) : (this.ativo + passo + n) % n;
                this.$nextTick(() => document.querySelector('.chatbot-prontas .ativa')?.scrollIntoView({ block: 'nearest' }));
            },
            escolher(pergunta) { this.ativo = -1; this.enviar(pergunta); },
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
                this.fechado = true; // não reabre a lista sozinha depois da resposta
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
                <x-filament::input.select wire:model.live="codigo" aria-label="Cliente em foco">
                    <option value="">Carteira inteira</option>
                    @foreach ($empresas as $e)
                        <option value="{{ $e->codigo }}">{{ $e->codigo }} · {{ $e->nome }}{{ $e->cancelada() ? ' (cancelado)' : '' }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </header>

    <div class="chatbot-log" role="log" aria-live="polite" aria-label="Conversa" x-effect="pendente; $wire.mensagens.length; $nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($mensagens as $m)
            <div class="chatbot-row {{ $m['eu'] ? 'eu' : '' }}">
                <div class="chatbot-msg">@if ($m['eu']){{ $m['texto'] }}@else<div class="chatbot-markdown">{!! \Illuminate\Support\Str::markdown($m['texto'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>@endif @isset($m['fonte'])<small>{{ $m['fonte'] }}</small>@endisset</div>
            </div>
        @empty
            <div class="chatbot-vazio" x-show="!pendente">
                <h2>{{ $foco ? 'Pergunte sobre '.$foco->nome : 'Como posso ajudar com a carteira?' }}</h2>
                <p>{{ $foco ? 'Tenho o contexto completo deste cliente: contrato, sinais de risco, NPS, histórico e cancelados parecidos.' : 'Escolha um cliente acima ou cite o código dele (ex.: C012) para uma resposta específica.' }}</p>
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
            <ul class="chatbot-prontas" id="chatbot-prontas" role="listbox" aria-label="Perguntas sugeridas" x-show="sugeridas.length" x-cloak>
                <template x-for="(p, i) in sugeridas" :key="p">
                    <li role="option" :aria-selected="i === ativo">
                        <button type="button" tabindex="-1" :class="{ 'ativa': i === ativo }" x-on:mousedown.prevent="escolher(p)" x-text="p"></button>
                    </li>
                </template>
            </ul>
            <input type="text" x-ref="campo" wire:model="pergunta" maxlength="500" autocomplete="off" autofocus
                   role="combobox" aria-autocomplete="list" aria-controls="chatbot-prontas" :aria-expanded="sugeridas.length > 0"
                   x-init="focado = document.activeElement === $el"
                   x-on:focus="focado = true; fechado = false" x-on:blur="focado = false" x-on:click="fechado = false"
                   x-on:input="texto = $event.target.value; ativo = -1; fechado = false"
                   x-on:keydown.arrow-down.prevent="fechado ? fechado = false : mover(1)" x-on:keydown.arrow-up.prevent="fechado ? fechado = false : mover(-1)"
                   x-on:keydown.escape="fechado = true; ativo = -1"
                   x-on:keydown.enter="if (ativo >= 0 && sugeridas[ativo]) { $event.preventDefault(); escolher(sugeridas[ativo]); }"
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
