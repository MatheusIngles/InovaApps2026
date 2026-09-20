<div class="pl">
    <section class="ui-card ui-pad">
        <h2>Enviar planilha</h2>
        <p class="ui-muted">Cada linha representa um cliente em um mês. A planilha precisa identificar cliente, mês, segmento, porte, plano e valor mensal do contrato; adicione qualquer quantidade de colunas de métricas. Arquivos futuros podem conter outras métricas.</p>
        <div class="ui-actions">
            <a class="ui-btn primary" href="{{ route('planilha.modelo.xlsx') }}">Baixar modelo completo (XLSX)</a>
            <button type="button" class="ui-btn" x-on:click="$dispatch('open-modal', { id: 'tutorial-importacao' })">Como preparar minha planilha</button>
            <a class="ui-btn" href="{{ route('planilha.modelo') }}">Modelo CSV básico</a>
        </div>
        <p class="ui-muted">O modelo XLSX traz uma aba Leia-me com as instruções e um dicionário dos campos. Você pode usar várias abas, desde que todas tenham a coluna cliente_id. Se preencher o dicionário (tipo, descrição, piora quando, valores e peso), as métricas já chegam configuradas na confirmação.</p>
        <p class="ui-muted">Colunas de métricas omitidas em novos arquivos preservam os valores já importados. Uma célula vazia em uma métrica enviada representa ausência de valor naquele mês.</p>

        <label class="pl-drop" wire:loading.class="pl-drop-busy" wire:target="arquivo">
            <input type="file" wire:model="arquivo" accept=".xlsx,.csv" class="pl-file">
            <strong>{{ $cabecalhos ? 'Enviar outra planilha' : 'Escolher arquivo' }}</strong>
            <span wire:loading.remove wire:target="arquivo">.xlsx ou .csv, até 20 MB</span>
            <span wire:loading wire:target="arquivo">Lendo a planilha…</span>
        </label>
        @error('arquivo')<p class="pl-erro" role="alert">{{ $message }}</p>@enderror
    </section>

    <x-filament::modal id="tutorial-importacao" width="4xl" heading="Como preparar sua planilha para importação">
        <div class="space-y-5 text-sm leading-6">
            <p>Você pode começar com uma base de clientes de qualquer origem. Organize os dados antes do envio para que cada valor seja associado ao cliente e ao mês corretos.</p>

            <section>
                <h3 class="font-semibold">1. Identifique as informações na sua base</h3>
                <p>Encontre uma identificação estável para cada cliente (código, matrícula ou outro identificador), o mês de cada registro, o porte e o valor mensal do contrato. Separe também as medidas que deseja acompanhar, como uso, chamados ou satisfação. Se a base tiver apenas nomes, crie um código único por cliente e use sempre o mesmo código nos meses seguintes.</p>
            </section>

            <section>
                <h3 class="font-semibold">2. Organize as linhas e colunas</h3>
                <p>Na primeira linha, escreva um cabeçalho para cada coluna, sem nomes vazios ou repetidos. Em uma tabela única (CSV ou XLSX), use uma linha para cada combinação de cliente e mês. Não repita o mesmo cliente no mesmo mês. O arquivo precisa ter ao menos uma coluna de métrica e um valor de métrica preenchido.</p>
                <div class="ui-table-wrap mt-2">
                    <table class="ui-table">
                        <thead><tr><th>cliente_id</th><th>mes_ref</th><th>porte</th><th>valor_mensal</th><th>uso_plataforma_pct</th><th>chamados_criticos</th></tr></thead>
                        <tbody>
                            <tr><td>C007</td><td>2026-01</td><td>Médio</td><td>3848,00</td><td>83</td><td>2</td></tr>
                            <tr><td>C007</td><td>2026-02</td><td>Médio</td><td>3848,00</td><td>76</td><td>3</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>O mês deve ser <strong>AAAA-MM</strong> (por exemplo, 2026-01). Também é aceita uma data no primeiro dia do mês, como 2026-01-01. O valor mensal deve ser positivo ou zero, com no máximo duas casas decimais.</p>
            </section>

            <section>
                <h3 class="font-semibold">3. Confira os campos necessários</h3>
                <ul class="list-disc pl-5">
                    <li><strong>Obrigatórios:</strong> código do cliente, mês de referência, porte e valor mensal do contrato. Esses valores devem estar preenchidos em todas as linhas.</li>
                    <li><strong>Opcionais:</strong> segmento e plano. Se não existirem, ficam como “Não informado”.</li>
                    <li><strong>Cadastro adicional:</strong> <code>situacao</code> (Ativo ou Cancelado), <code>mes_cancelamento</code> (AAAA-MM) e <code>inicio_contrato</code> (AAAA-MM-DD ou DD/MM/AAAA). Informe o mês de cancelamento para clientes Cancelados; não o informe para clientes Ativos.</li>
                    <li><strong>Métricas:</strong> cada outra coluna representa uma medida. Dê nomes claros, mantenha um único tipo de valor por coluna e deixe a célula vazia quando não houver dado naquele mês.</li>
                </ul>
            </section>

            <section>
                <h3 class="font-semibold">4. Se seus dados estão em várias abas</h3>
                <p>Use um arquivo XLSX. Todas as abas de dados precisam da coluna <code>cliente_id</code>, com o mesmo código para o mesmo cliente. Abas com <code>mes_ref</code> guardam dados mensais; abas sem essa coluna guardam dados de cadastro que valem para todos os meses. Tenha pelo menos uma aba mensal e não repita uma coluna de dados em abas diferentes. As abas “Leia-me” e “dicionario” do modelo são apenas de apoio.</p>
            </section>

            <section>
                <h3 class="font-semibold">5. Configure suas métricas e envie</h3>
                <p>No XLSX, você pode preencher a aba <code>dicionario</code> com o nome exato da coluna em <code>campo</code>, tipo, descrição, direção de piora, valor saudável, valor crítico e peso. Para métricas numéricas, informe se pioram quando aumentam ou diminuem; o valor crítico deve ficar nessa direção em relação ao saudável. O peso vai de 0 a 100. Textos e datas ficam no histórico e não entram no cálculo da atenção.</p>
                <p>Salve como <strong>.xlsx ou .csv</strong> (até 20 MB), escolha o arquivo acima e confira a prévia. Na tela de confirmação, associe as colunas da sua base aos campos obrigatórios, revise cada métrica nova ou vincule-a a uma existente e só então clique em <strong>Confirmar e importar</strong>. O CSV básico contém apenas os cabeçalhos estruturais: acrescente pelo menos uma coluna de métrica antes de enviar.</p>
            </section>
        </div>
        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'tutorial-importacao' })">Fechar tutorial</x-filament::button>
        </x-slot>
    </x-filament::modal>

    @if ($cabecalhos && $formatoPadrao)
        <section class="ui-card ui-pad">
            <h2>Planilha reconhecida</h2>
            <p class="ui-muted">As colunas estão no formato padrão do Seer: nada precisa ser mapeado. Situação e mês de cancelamento, quando existirem, marcam quem cancelou.</p>
            <form wire:submit="importar" class="pl-form">
                <div class="ui-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="importar">Importar planilha</x-filament::button></div>
            </form>
        </section>
    @elseif ($cabecalhos)
        <section class="ui-card ui-pad">
            <h2>Confirmar colunas</h2>
            <p class="ui-muted">Confira o mapeamento antes de importar. Nenhuma métrica nova será cadastrada até você confirmar.</p>
            <h3 class="mp-titulo">Colunas obrigatórias</h3>
            <form wire:submit="importar" class="pl-form">
                <div class="metric-grid">
                    @foreach (\App\Support\Import\DynamicImportService::STRUCTURE as $field => $label)
                        @php $opcional = in_array($field, \App\Support\Import\DynamicImportService::ESTRUTURA_OPCIONAL, true); @endphp
                        <label><span>{{ $label }}@if ($opcional) <small class="pl-opc">opcional</small>@endif</span>
                            <select class="fi-input" wire:model.change="structuralMapping.{{ $field }}" @required(! $opcional)>
                                <option value="">{{ $opcional ? 'Não informar (fica como "Não informado")' : 'Selecione uma coluna' }}</option>
                                @foreach ($cabecalhos as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>

                @php
                    $definicoes = app(\App\Support\Tenancy\CompanyContext::class)->current()->metricDefinitions()->orderBy('code')->get();
                    $reconhecidas = \App\Support\Import\DynamicImportService::colunasOpcionais($cabecalhos);
                    $precisamAjuste = collect($metricMappings)->filter(function ($m) {
                        $semScore = \App\Models\MetricDefinition::semScore($m['value_type'] ?? 'decimal');

                        return ($m['target'] ?? 'new') === 'new' && (trim((string) ($m['description'] ?? '')) === ''
                            || (! $semScore && ((string) ($m['direction'] ?? '') === '' || (string) ($m['healthy_value'] ?? '') === '' || (string) ($m['critical_value'] ?? '') === '')));
                    })->count();
                @endphp

                <div class="mp-cab">
                    <h3>Métricas encontradas ({{ count($metricMappings) }})</h3>
                    <p class="ui-muted">
                        @if ($precisamAjuste)
                            <span class="mp-chip aviso">{{ $precisamAjuste }} {{ $precisamAjuste === 1 ? 'precisa' : 'precisam' }} de ajuste</span>
                        @else
                            <span class="mp-chip ok">Tudo preenchido</span>
                        @endif
                        Clique em uma métrica para ver ou editar.
                        @if ($reconhecidas)
                            Reconhecidas como dados do cliente, e não como métrica: <strong>{{ implode(', ', array_values($reconhecidas)) }}</strong>.
                        @endif
                    </p>
                </div>

                @foreach ($metricMappings as $index => $metric)
                    @php
                        $nova = ($metric['target'] ?? 'new') === 'new';
                        $tipo = $metric['value_type'] ?? 'decimal';
                        $semScore = \App\Models\MetricDefinition::semScore($tipo);
                        $vinculada = $nova ? null : $definicoes->firstWhere('id', (int) $metric['target']);
                        $faltaAlgo = $nova && (trim((string) ($metric['description'] ?? '')) === ''
                            || (! $semScore && ((string) ($metric['direction'] ?? '') === '' || (string) ($metric['healthy_value'] ?? '') === '' || (string) ($metric['critical_value'] ?? '') === '')));
                        $resumo = $nova
                            ? ($semScore ? 'Fica só no histórico, fora do cálculo' : trim(
                                (($metric['direction'] ?? '') === 'lower' ? 'Piora quando diminui' : (($metric['direction'] ?? '') === 'higher' ? 'Piora quando aumenta' : 'Defina quando piora'))
                                .((string) ($metric['healthy_value'] ?? '') !== '' && (string) ($metric['critical_value'] ?? '') !== '' ? ' · saudável '.(float) $metric['healthy_value'].' · crítico '.(float) $metric['critical_value'] : '')
                                .' · peso '.(float) ($metric['weight'] ?? 0)))
                            : 'Os valores entram na métrica já cadastrada';
                    @endphp
                    <div class="mp {{ $faltaAlgo ? 'falta' : '' }}" wire:key="metric-column-{{ $index }}" x-data="{ aberto: @js($faltaAlgo) }">
                        <button type="button" class="mp-topo" x-on:click="aberto = ! aberto" :aria-expanded="aberto" aria-controls="mp-corpo-{{ $index }}">
                            <span class="mp-nome">{{ $metric['column'] }}</span>
                            <span class="mp-chip {{ $nova ? '' : 'vinc' }}">{{ $nova ? 'Nova métrica' : 'Vinculada a '.($vinculada->label ?? '—') }}</span>
                            <span class="mp-chip tipo">{{ \App\Models\MetricDefinition::TYPES[$tipo] ?? $tipo }}</span>
                            @if ($faltaAlgo)<span class="mp-chip aviso">Falta preencher</span>@endif
                            <span class="mp-resumo">{{ $resumo }}</span>
                            <svg class="mp-seta" :class="{ 'gira': aberto }" viewBox="0 0 20 20" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M5.5 7.5 10 12l4.5-4.5-1-1L10 10 6.5 6.5z"/></svg>
                        </button>

                        <div class="mp-corpo" id="mp-corpo-{{ $index }}" x-show="aberto" x-cloak>
                            <div class="mp-seg" role="group" aria-label="O que fazer com a coluna {{ $metric['column'] }}">
                                <button type="button" :class="{ 'on': {{ $nova ? 'true' : 'false' }} }" aria-pressed="{{ $nova ? 'true' : 'false' }}" wire:click="$set('metricMappings.{{ $index }}.target', 'new')">Criar métrica nova</button>
                                <button type="button" :class="{ 'on': {{ $nova ? 'false' : 'true' }} }" aria-pressed="{{ $nova ? 'false' : 'true' }}" wire:click="usarExistente({{ $index }})" @disabled($definicoes->isEmpty())>Vincular a uma existente</button>
                            </div>

                            @if (! $nova)
                                <label class="mp-campo">Métrica existente
                                    <select class="fi-input" wire:model.change="metricMappings.{{ $index }}.target">
                                        @foreach ($definicoes as $definition)
                                            <option value="{{ $definition->id }}">{{ $definition->label }} ({{ $definition->code }})</option>
                                        @endforeach
                                    </select>
                                </label>
                            @else
                                <div class="mp-linha">
                                    <label class="mp-campo">Nome<input class="fi-input" wire:model="metricMappings.{{ $index }}.label" maxlength="100"></label>
                                    <label class="mp-campo">Código<input class="fi-input" wire:model="metricMappings.{{ $index }}.code" maxlength="40" pattern="[a-z][a-z0-9_]*"></label>
                                </div>

                                <fieldset class="mp-grupo">
                                    <legend>Tipo do valor</legend>
                                    <div class="mp-tipos">
                                        @foreach (\App\Models\MetricDefinition::TYPES as $chave => $rotuloTipo)
                                            <label class="{{ $tipo === $chave ? 'on' : '' }}">
                                                <input type="radio" wire:model.live="metricMappings.{{ $index }}.value_type" value="{{ $chave }}" name="tipo-{{ $index }}">
                                                {{ preg_replace('/ \(.*/', '', $rotuloTipo) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>

                                <label class="mp-campo">Descrição<textarea class="fi-input" wire:model="metricMappings.{{ $index }}.description" rows="2" maxlength="1000" placeholder="O que essa métrica mede?"></textarea></label>

                                @if ($semScore)
                                    <p class="ui-muted">Datas e textos ficam guardados no histórico do cliente, mas não entram no cálculo da atenção.</p>
                                @else
                                    <fieldset class="mp-grupo">
                                        <legend>Quando piora</legend>
                                        <div class="mp-seg" role="radiogroup">
                                            <button type="button" role="radio" :class="{ 'on': {{ ($metric['direction'] ?? '') === 'lower' ? 'true' : 'false' }} }" aria-checked="{{ ($metric['direction'] ?? '') === 'lower' ? 'true' : 'false' }}" wire:click="$set('metricMappings.{{ $index }}.direction', 'lower')">Quando diminui</button>
                                            <button type="button" role="radio" :class="{ 'on': {{ ($metric['direction'] ?? '') === 'higher' ? 'true' : 'false' }} }" aria-checked="{{ ($metric['direction'] ?? '') === 'higher' ? 'true' : 'false' }}" wire:click="$set('metricMappings.{{ $index }}.direction', 'higher')">Quando aumenta</button>
                                        </div>
                                    </fieldset>
                                    <div class="mp-linha tres">
                                        <label class="mp-campo">Valor saudável<input class="fi-input" type="number" step="any" wire:model="metricMappings.{{ $index }}.healthy_value"></label>
                                        <label class="mp-campo">Valor crítico<input class="fi-input" type="number" step="any" wire:model="metricMappings.{{ $index }}.critical_value"></label>
                                        <label class="mp-campo">Peso (0 a 100)<input class="fi-input" type="number" min="0" max="100" step="0.01" wire:model="metricMappings.{{ $index }}.weight"></label>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
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
