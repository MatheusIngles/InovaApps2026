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
            @if ($newMetric['value_type'] !== 'text')
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

    @if ($definitions->isNotEmpty())
        <section class="ui-card ui-pad">
            <h2>Métricas cadastradas</h2>
            <p class="ui-muted">Após alterar peso, faixa ou estado, a atenção da carteira é recalculada. Desativar uma métrica mantém seus valores históricos.</p>
            @foreach ($definitions as $definition)
                <form wire:submit="saveDefinition({{ $definition->id }})" class="pl-form metric-entry">
                    <h3>{{ $definition->code }}</h3>
                    <div class="metric-grid">
                        <label>Nome<input class="fi-input" type="text" wire:model="edits.{{ $definition->id }}.label" required></label>
                        <label>Tipo<select class="fi-input" wire:model.change="edits.{{ $definition->id }}.value_type" @disabled($definition->values_count)>@foreach (\App\Models\MetricDefinition::TYPES as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label>
                    </div>
                    <label>Descrição<textarea class="fi-input" wire:model="edits.{{ $definition->id }}.description" rows="2"></textarea></label>
                    @if (($edits[$definition->id]['value_type'] ?? $definition->value_type) !== 'text')
                    <div class="metric-grid">
                        <label>Quando piora<select class="fi-input" wire:model="edits.{{ $definition->id }}.direction"><option value="lower">Quando diminui</option><option value="higher">Quando aumenta</option></select></label>
                        <label>Valor saudável<input class="fi-input" type="number" step="any" wire:model="edits.{{ $definition->id }}.healthy_value" required></label>
                        <label>Valor crítico<input class="fi-input" type="number" step="any" wire:model="edits.{{ $definition->id }}.critical_value" required></label>
                        <label>Peso<input class="fi-input" type="number" min="0" max="100" step="0.01" wire:model="edits.{{ $definition->id }}.weight" required></label>
                    </div>
                    @endif
                    @if (($edits[$definition->id]['value_type'] ?? $definition->value_type) !== 'text')
                    <div class="metric-grid">
                        <label><input type="checkbox" wire:model="edits.{{ $definition->id }}.enabled"> Considerar no cálculo</label>
                    </div>
                    @endif
                    @foreach (['label', 'description', 'value_type', 'direction', 'healthy_value', 'critical_value', 'weight', 'enabled'] as $field)
                        @error('edits.'.$definition->id.'.'.$field)<p class="pl-erro" role="alert">{{ $message }}</p>@enderror
                    @endforeach
                    <div class="ui-actions"><x-filament::button type="submit" color="gray">Salvar métrica</x-filament::button></div>
                </form>
            @endforeach
        </section>
    @endif
</div>
