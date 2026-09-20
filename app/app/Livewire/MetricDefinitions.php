<?php

namespace App\Livewire;

use App\Models\MetricDefinition;
use App\Support\Risco;
use App\Support\RiskService;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class MetricDefinitions extends Component
{
    public array $newMetric = [
        'code' => '', 'label' => '', 'description' => '', 'value_type' => 'decimal', 'direction' => 'lower',
        'healthy_value' => '', 'critical_value' => '', 'weight' => '10',
    ];

    public array $edits = [];

    public function mount(): void
    {
        $this->refreshEdits();
    }

    public function create(): void
    {
        $company = app(CompanyContext::class)->current();
        $text = MetricDefinition::semScore($this->newMetric['value_type'] ?? null);
        $data = $this->validate([
            'newMetric.code' => ['required', 'regex:/^[a-z][a-z0-9_]{0,39}$/', Rule::unique('metric_definitions', 'code')->where('company_id', $company->id)],
            'newMetric.label' => 'required|string|max:100',
            'newMetric.description' => 'required|string|max:1000',
            'newMetric.value_type' => ['required', Rule::in(array_keys(MetricDefinition::TYPES))],
            'newMetric.direction' => 'required|in:lower,higher',
            'newMetric.healthy_value' => ($text ? 'nullable' : 'required').'|numeric|between:-9999999999,9999999999',
            'newMetric.critical_value' => ($text ? 'nullable' : 'required').'|numeric|between:-9999999999,9999999999',
            'newMetric.weight' => 'required|numeric|between:0,100',
        ])['newMetric'];
        if (MetricDefinition::semScore($data['value_type'])) {
            $data['weight'] = 0;
            $data['healthy_value'] = 0;
            $data['critical_value'] = 1;
        } else {
            $this->validateDirection($data, 'newMetric.critical_value');
        }

        $company->metricDefinitions()->create($data + ['enabled' => true]);
        Backtest::invalidar($company);
        $this->newMetric = ['code' => '', 'label' => '', 'description' => '', 'value_type' => 'decimal', 'direction' => 'lower', 'healthy_value' => '', 'critical_value' => '', 'weight' => '10'];
        $this->refreshEdits();
        RiskService::recalcular($company);
        Notification::make()->title('Métrica cadastrada.')->success()->send();
    }

    public function saveDefinition(int $id): void
    {
        $company = app(CompanyContext::class)->current();
        $definition = $company->metricDefinitions()->findOrFail($id);
        $data = $this->validate([
            "edits.$id.label" => 'required|string|max:100',
            "edits.$id.description" => 'nullable|string|max:1000',
            "edits.$id.value_type" => ['required', Rule::in(array_keys(MetricDefinition::TYPES))],
            "edits.$id.direction" => 'required|in:lower,higher',
            "edits.$id.healthy_value" => 'required|numeric|between:-9999999999,9999999999',
            "edits.$id.critical_value" => 'required|numeric|between:-9999999999,9999999999',
            "edits.$id.weight" => 'required|numeric|between:0,100',
            "edits.$id.enabled" => 'boolean',
        ])['edits'][$id];
        if ($definition->value_type !== $data['value_type'] && $definition->values()->exists()) {
            throw ValidationException::withMessages(["edits.$id.value_type" => 'Não altere o tipo de uma métrica que já possui valores.']);
        }
        if (MetricDefinition::semScore($data['value_type'])) {
            $data['weight'] = 0;
            $data['healthy_value'] = 0;
            $data['critical_value'] = 1;
        } else {
            $this->validateDirection($data, "edits.$id.critical_value");
        }

        $definition->update($data);
        Backtest::invalidar($company);
        RiskService::recalcular($company);
        Notification::make()->title('Métrica atualizada e atenção recalculada.')->success()->send();
    }

    private function validateDirection(array $data, string $field): void
    {
        $healthy = (float) $data['healthy_value'];
        $critical = (float) $data['critical_value'];
        if (($data['direction'] === 'higher' && $critical <= $healthy)
            || ($data['direction'] === 'lower' && $critical >= $healthy)) {
            throw ValidationException::withMessages([$field => 'O valor crítico deve ficar na direção de piora em relação ao saudável.']);
        }
    }

    private function refreshEdits(): void
    {
        $company = app(CompanyContext::class)->current();
        $this->edits = $company->metricDefinitions()->orderBy('code')->get()->mapWithKeys(fn (MetricDefinition $definition): array => [
            $definition->id => $definition->only(['label', 'description', 'value_type', 'direction', 'healthy_value', 'critical_value', 'weight', 'enabled']),
        ])->all();
    }

    public function render()
    {
        $company = app(CompanyContext::class)->current();

        $definitions = $company->metricDefinitions()->withCount('values')->orderBy('code')->get();
        $padrao = $company->hasLegacyMetrics() ? collect($company->pesos())->filter(fn ($peso): bool => $peso > 0) : collect();
        $proprias = $definitions->filter(fn (MetricDefinition $definition): bool => $definition->enabled && ! MetricDefinition::semScore($definition->value_type) && (float) $definition->weight > 0);
        $total = $padrao->sum() + $proprias->sum(fn (MetricDefinition $definition): float => (float) $definition->weight);
        $pct = fn (float $peso): float => $total > 0 ? round($peso / $total * 100, 1) : 0.0;

        return view('livewire.metric-definitions', [
            'definitions' => $definitions,
            'padrao' => $padrao->map(fn ($peso, string $chave): array => ['rotulo' => Risco::ROTULOS[$chave], 'pct' => $pct((float) $peso)])->values(),
            'participacao' => $proprias->mapWithKeys(fn (MetricDefinition $definition): array => [$definition->id => $pct((float) $definition->weight)])->all(),
        ]);
    }
}
