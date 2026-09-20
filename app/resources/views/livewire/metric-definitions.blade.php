<div class="pl">
    <section class="ui-card ui-pad">
        <h2>Métricas da empresa</h2>
        <p class="ui-muted">Métricas novas também podem ser descobertas durante o upload. Aqui você pode ajustar nome, descrição, interpretação e peso. Códigos ficam fixos para preservar o histórico.</p>

        <form wire:submit="create" class="pl-form">
            <div class="metric-grid">
                <label>Código<input class="fi-input" type="text" wire:model="newMetric.code" placeholder="ex.: pedidos_recorrentes" required></label>
                <label>Nome<input class="fi-input" type="text" wire:model="newMetric.label" placeholder="Ex.: Pedidos recorrentes" required></label>
                <label>Tipo<select class="fi-input" wire:model.change="newMetric.value_type">@foreach (\App\Models\MetricDefinition::TYPES as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label>
            </div>
            <label>Descrição<textarea class="fi-input" wire:model="newMetric.description" rows="2" required></textarea></label>
            @if (! \App\Models\MetricDefinition::semScore($newMetric['value_type']))
                <div class="metric-grid">
                    <label>Quando piora<select class="fi-input" wire:model="newMetric.direction"><option value="lower">Quando diminui</option><option value="higher">Quando aumenta</option></select></label>
                    <label>Valor saudável<input class="fi-input" type="number" step="any" wire:model="newMetric.healthy_value" required></label>
                    <label>Valor crítico<input class="fi-input" type="number" step="any" wire:model="newMetric.critical_value" required></label>
                    <label>Peso<input class="fi-input" type="number" min="0" max="100" step="0.01" wire:model="newMetric.weight" required></label>
                </div>
            @endif
            @foreach (['code', 'label', 'description', 'value_type', 'direction', 'healthy_value', 'critical_value', 'weight'] as $field)
                @error('newMetric.'.$field)<p class="pl-erro" role="alert">{{ $message }}</p>@enderror
            @endforeach
            <div class="ui-actions"><x-filament::button type="submit">Cadastrar métrica</x-filament::button></div>
        </form>
    </section>

    @if ($definitions->isNotEmpty() || $padrao->isNotEmpty())
        <section class="ui-card ui-pad">
            <div class="mp-cab">
                <h2>Métricas cadastradas</h2>
                <p class="ui-muted">Toque em uma métrica para editar. Após alterar peso, faixa ou estado, a atenção da carteira é recalculada. Desativar uma métrica mantém seus valores históricos.</p>
            </div>

            <h3 class="mp-titulo">Participação de cada fator na atenção</h3>
            <p class="ui-muted">Os sinais padrão e as métricas da empresa entram na mesma conta: a participação é o peso de cada um dividido pela soma dos pesos.</p>
            <ul class="mp-part" aria-label="Participação de cada fator na atenção">
                @foreach ($padrao as $fator)
                    <li><span class="mp-part-nome">{{ $fator['rotulo'] }} <span class="mp-chip tipo">Sinal padrão</span></span><span class="mp-part-barra" aria-hidden="true"><i style="width: {{ $fator['pct'] }}%"></i></span><span class="mp-part-pct">{{ number_format($fator['pct'], 1, ',', '') }}%</span></li>
                @endforeach
                @foreach ($definitions as $definition)
                    @continue(! isset($participacao[$definition->id]))
                    <li><span class="mp-part-nome">{{ $definition->label }}</span><span class="mp-part-barra" aria-hidden="true"><i style="width: {{ $participacao[$definition->id] }}%"></i></span><span class="mp-part-pct">{{ number_format($participacao[$definition->id], 1, ',', '') }}%</span></li>
                @endforeach
            </ul>
            @if ($padrao->isNotEmpty())
                <p class="ui-muted">A ordem e o peso dos sinais padrão se ajustam na aba <strong>Prioridades</strong>.</p>
            @endif

            @foreach ($definitions as $definition)
                @php
                    $edit = $edits[$definition->id] ?? [];
                    $tipo = $edit['value_type'] ?? $definition->value_type;
                    $semScore = \App\Models\MetricDefinition::semScore($tipo);
                    $ligada = (bool) ($edit['enabled'] ?? $definition->enabled);
                    $direcao = $edit['direction'] ?? $definition->direction;
                    $resumo = $semScore
                        ? 'Guardada no histórico, fora do cálculo da atenção'
                        : 'Piora quando '.($definition->direction === 'higher' ? 'aumenta' : 'diminui').' · saudável '.rtrim(rtrim(number_format((float) $definition->healthy_value, 4, ',', ''), '0'), ',').' · crítico '.rtrim(rtrim(number_format((float) $definition->critical_value, 4, ',', ''), '0'), ',').' · peso '.rtrim(rtrim(number_format((float) $definition->weight, 2, ',', ''), '0'), ',');
                @endphp
                <div class="mp" wire:key="definicao-{{ $definition->id }}" x-data="{ aberto: false }">
                    <button type="button" class="mp-topo" x-on:click="aberto = ! aberto" :aria-expanded="aberto" aria-controls="mp-def-{{ $definition->id }}">
                        <span class="mp-nome">{{ $definition->label }}</span>
                        <span class="mp-chip tipo">{{ preg_replace('/ \(.*/', '', \App\Models\MetricDefinition::TYPES[$definition->value_type] ?? $definition->value_type) }}</span>
                        @if ($semScore)
                            <span class="mp-chip">Fora do cálculo</span>
                        @elseif ($definition->enabled)
                            <span class="mp-chip ok">Na atenção{{ isset($participacao[$definition->id]) ? ' · '.number_format($participacao[$definition->id], 1, ',', '').'%' : '' }}</span>
                        @else
                            <span class="mp-chip aviso">Desativada</span>
                        @endif
                        <span class="mp-resumo">{{ $resumo }}</span>
                        <svg class="mp-seta" :class="{ 'gira': aberto }" viewBox="0 0 20 20" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M5.5 7.5 10 12l4.5-4.5-1-1L10 10 6.5 6.5z"/></svg>
                    </button>

                    <form wire:submit="saveDefinition({{ $definition->id }})" class="mp-corpo" id="mp-def-{{ $definition->id }}" x-show="aberto" x-cloak>
                        <p class="ui-muted">Código <code>{{ $definition->code }}</code> (fixo, para preservar o histórico){{ $definition->values_count ? ' · '.number_format($definition->values_count, 0, ',', '.').' valores guardados; o tipo não pode mudar' : '' }}</p>
                        <div class="mp-linha">
                            <label class="mp-campo">Nome<input class="fi-input" type="text" wire:model="edits.{{ $definition->id }}.label" required maxlength="100"></label>
                            <label class="mp-campo">Tipo<select class="fi-input" wire:model.change="edits.{{ $definition->id }}.value_type" @disabled($definition->values_count)>@foreach (\App\Models\MetricDefinition::TYPES as $chave => $rotuloTipo)<option value="{{ $chave }}">{{ $rotuloTipo }}</option>@endforeach</select></label>
                        </div>
                        <label class="mp-campo">Descrição<textarea class="fi-input" wire:model="edits.{{ $definition->id }}.description" rows="2" maxlength="1000" placeholder="O que essa métrica mede?"></textarea></label>

                        @unless ($semScore)
                            <div class="mp-linha">
                                <fieldset class="mp-grupo">
                                    <legend>Quando piora</legend>
                                    <div class="mp-seg" role="radiogroup">
                                        <button type="button" role="radio" :class="{ 'on': {{ $direcao === 'higher' ? 'true' : 'false' }} }" aria-checked="{{ $direcao === 'higher' ? 'true' : 'false' }}" wire:click="$set('edits.{{ $definition->id }}.direction', 'higher')">Quando aumenta</button>
                                        <button type="button" role="radio" :class="{ 'on': {{ $direcao === 'lower' ? 'true' : 'false' }} }" aria-checked="{{ $direcao === 'lower' ? 'true' : 'false' }}" wire:click="$set('edits.{{ $definition->id }}.direction', 'lower')">Quando diminui</button>
                                    </div>
                                </fieldset>
                                <fieldset class="mp-grupo">
                                    <legend>Considerar no cálculo da atenção</legend>
                                    <div class="mp-seg" role="radiogroup">
                                        <button type="button" role="radio" :class="{ 'on': {{ $ligada ? 'true' : 'false' }} }" aria-checked="{{ $ligada ? 'true' : 'false' }}" wire:click="$set('edits.{{ $definition->id }}.enabled', true)">Ligada</button>
                                        <button type="button" role="radio" :class="{ 'on': {{ $ligada ? 'false' : 'true' }} }" aria-checked="{{ $ligada ? 'false' : 'true' }}" wire:click="$set('edits.{{ $definition->id }}.enabled', false)">Desligada</button>
                                    </div>
                                </fieldset>
                            </div>
                            <div class="mp-linha tres">
                                <label class="mp-campo">Valor saudável<input class="fi-input" type="number" step="any" wire:model="edits.{{ $definition->id }}.healthy_value" required></label>
                                <label class="mp-campo">Valor crítico<input class="fi-input" type="number" step="any" wire:model="edits.{{ $definition->id }}.critical_value" required></label>
                                <label class="mp-campo">Peso (0 a 100)<input class="fi-input" type="number" min="0" max="100" step="0.01" wire:model="edits.{{ $definition->id }}.weight" required></label>
                            </div>
                        @endunless

                        @foreach (['label', 'description', 'value_type', 'direction', 'healthy_value', 'critical_value', 'weight', 'enabled'] as $field)
                            @error('edits.'.$definition->id.'.'.$field)<p class="pl-erro" role="alert">{{ $message }}</p>@enderror
                        @endforeach
                        <div class="ui-actions"><x-filament::button type="submit" color="gray">Salvar métrica</x-filament::button></div>
                    </form>
                </div>
            @endforeach
        </section>
    @endif
</div>
