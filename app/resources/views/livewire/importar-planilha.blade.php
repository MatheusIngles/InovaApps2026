<div class="pl">
    <section class="ui-card ui-pad">
        <h2>Enviar planilha</h2>
        <p class="ui-muted">Cada linha representa um cliente em um mês. A planilha precisa identificar cliente, mês, segmento, porte, plano e valor mensal do contrato; adicione qualquer quantidade de colunas de métricas. Arquivos futuros podem conter outras métricas.</p>
        <p><a class="ui-btn" href="{{ route('planilha.modelo') }}">Baixar modelo CSV básico</a></p>
        <p class="ui-muted">Colunas de métricas omitidas em novos arquivos preservam os valores já importados. Uma célula vazia em uma métrica enviada representa ausência de valor naquele mês.</p>

        <label class="pl-drop" wire:loading.class="pl-drop-busy" wire:target="arquivo">
            <input type="file" wire:model="arquivo" accept=".xlsx,.csv" class="pl-file">
            <strong>{{ $cabecalhos ? 'Enviar outra planilha' : 'Escolher arquivo' }}</strong>
            <span wire:loading.remove wire:target="arquivo">.xlsx ou .csv, até 20 MB</span>
            <span wire:loading wire:target="arquivo">Lendo a planilha…</span>
        </label>
        @error('arquivo')<p class="pl-erro" role="alert">{{ $message }}</p>@enderror
    </section>

    @if ($cabecalhos)
        <section class="ui-card ui-pad">
            <h2>Confirmar colunas</h2>
            <p class="ui-muted">Confira o mapeamento antes de importar. Nenhuma métrica nova será cadastrada até você confirmar.</p>
            <form wire:submit="importar" class="pl-form">
                <div class="metric-grid">
                    @foreach (\App\Support\Import\DynamicImportService::STRUCTURE as $field => $label)
                        <label>{{ $label }}
                            <select class="fi-input" wire:model.change="structuralMapping.{{ $field }}" required>
                                <option value="">Selecione uma coluna</option>
                                @foreach ($cabecalhos as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>

                @foreach ($metricMappings as $index => $metric)
                    <fieldset class="metric-entry pl-form" wire:key="metric-column-{{ $index }}">
                        <legend><strong>{{ $metric['column'] }}</strong></legend>
                        <label>Vincular a
                            <select class="fi-input" wire:model.change="metricMappings.{{ $index }}.target">
                                <option value="new">Nova métrica</option>
                                @foreach (app(\App\Support\Tenancy\CompanyContext::class)->current()->metricDefinitions()->orderBy('code')->get() as $definition)
                                    <option value="{{ $definition->id }}">{{ $definition->label }} ({{ $definition->code }})</option>
                                @endforeach
                            </select>
                        </label>
                        @if (($metric['target'] ?? 'new') === 'new')
                            <div class="metric-grid">
                                <label>Nome<input class="fi-input" wire:model="metricMappings.{{ $index }}.label" required maxlength="100"></label>
                                <label>Código<input class="fi-input" wire:model="metricMappings.{{ $index }}.code" required maxlength="40" pattern="[a-z][a-z0-9_]*"></label>
                                <label>Tipo
                                    <select class="fi-input" wire:model.change="metricMappings.{{ $index }}.value_type">
                                        @foreach (\App\Models\MetricDefinition::TYPES as $type => $typeLabel)
                                            <option value="{{ $type }}">{{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <label>Descrição<textarea class="fi-input" wire:model="metricMappings.{{ $index }}.description" rows="2" maxlength="1000" required></textarea></label>
                            @if (($metric['value_type'] ?? 'decimal') !== 'text')
                                <div class="metric-grid">
                                    <label>Quando piora
                                        <select class="fi-input" wire:model="metricMappings.{{ $index }}.direction" required>
                                            <option value="">Selecione</option>
                                            <option value="lower">Quando diminui</option>
                                            <option value="higher">Quando aumenta</option>
                                        </select>
                                    </label>
                                    <label>Valor saudável<input class="fi-input" type="number" step="any" wire:model="metricMappings.{{ $index }}.healthy_value" required></label>
                                    <label>Valor crítico<input class="fi-input" type="number" step="any" wire:model="metricMappings.{{ $index }}.critical_value" required></label>
                                    <label>Peso<input class="fi-input" type="number" min="0" max="100" step="0.01" wire:model="metricMappings.{{ $index }}.weight" required></label>
                                </div>
                            @else
                                <p class="ui-muted">Valores textuais ficam armazenados no histórico, mas não entram no score.</p>
                            @endif
                        @endif
                    </fieldset>
                @endforeach

                <div class="ui-table-wrap">
                    <p class="ui-muted">Prévia das primeiras linhas:</p>
                    <table class="ui-table">
                        <thead><tr>@foreach ($cabecalhos as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($previa as $linha)
                                <tr>@foreach ($cabecalhos as $h)<td>{{ $linha[$h] ?? '' }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="ui-actions">
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="importar">Confirmar e importar</x-filament::button>
                    <x-filament::button color="gray" wire:click="descartar">Cancelar</x-filament::button>
                </div>
            </form>
        </section>
    @endif

    @if ($resultado)
        <section class="ui-card ui-pad" role="status">
            <h2>Importação concluída</h2>
            <p class="ui-about">{{ $resultado['clientes'] }} clientes · {{ $resultado['meses'] }} registros mensais · {{ $resultado['valores_metricas'] }} valores · {{ $resultado['novas_metricas'] }} métricas novas.</p>
        </section>
    @endif
</div>
