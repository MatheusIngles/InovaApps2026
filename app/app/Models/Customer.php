<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Risco;
use App\Support\Tenancy\CompanyContext;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['company_id', 'external_code', 'segment', 'size', 'plan', 'monthly_value', 'contracted_sla_hours', 'contract_started_at', 'status', 'cancelled_at'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToCompany, HasFactory;

    public function metrics(): HasMany
    {
        return $this->hasMany(CustomerMetric::class);
    }

    public function npsResponses(): HasMany
    {
        return $this->hasMany(CustomerNps::class);
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class);
    }

    public function currentAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class)->where('model_version', 'rules-v1')->latestOfMany('reference_month');
    }

    public function getRouteKeyName(): string
    {
        return 'external_code';
    }

    public static function dashboard(): Builder
    {
        $latest = DB::table('risk_assessments')->selectRaw('customer_id, MAX(reference_month) as reference_month')
            ->where('model_version', 'rules-v1')->groupBy('customer_id');

        return static::query()->leftJoinSub($latest, 'latest_assessment', 'customers.id', '=', 'latest_assessment.customer_id')
            ->leftJoin('risk_assessments as assessment', fn ($join) => $join
                ->on('assessment.customer_id', '=', 'customers.id')
                ->on('assessment.reference_month', '=', 'latest_assessment.reference_month')
                ->where('assessment.model_version', '=', 'rules-v1'))
            ->select('customers.*', 'assessment.health_score as score', 'assessment.priority_score as exposicao')
            ->with('currentAssessment');
    }

    public static function ordenar(Builder $query): Builder
    {
        // canceladas por último. Entre as ativas: regra de três (risco × valor do contrato) com um reforço para risco alto:
        // score × (score + K) × valor, com K configurável por empresa (Configurações).
        $k = app(CompanyContext::class)->current()?->prioridadeK() ?? Company::PRIORIDADE_PADRAO;

        return $query->orderByRaw("customers.status = 'Cancelado'")
            ->orderByRaw('assessment.health_score * (assessment.health_score + ?) * customers.monthly_value DESC', [$k]);
    }

    public static function ativas(): Collection
    {
        return static::ordenar(static::dashboard()->where('customers.status', 'Ativo'))->get();
    }

    public function displayName(): string
    {
        return "{$this->segment} {$this->external_code}";
    }

    public function cancelada(): bool
    {
        return $this->status === 'Cancelado';
    }

    public function rotulo(): string
    {
        return $this->cancelada() ? 'Cancelada' : $this->nivel;
    }

    public static function brl(float|int $value): string
    {
        return 'R$ '.number_format($value, 0, ',', '.');
    }

    public function getCodigoAttribute(): string
    {
        return $this->external_code;
    }

    public function getNomeAttribute(): string
    {
        return $this->displayName();
    }

    public function getSegmentoAttribute(): string
    {
        return $this->segment;
    }

    public function getPorteAttribute(): string
    {
        return $this->size;
    }

    public function getPlanoAttribute(): string
    {
        return $this->plan;
    }

    public function getValorAttribute(): float
    {
        return (float) $this->monthly_value;
    }

    public function getSlaHAttribute(): int
    {
        return $this->contracted_sla_hours;
    }

    public function getInicioAttribute(): string
    {
        return $this->contract_started_at->toDateString();
    }

    public function getMesCancelAttribute(): ?string
    {
        return $this->cancelled_at?->format('Y-m');
    }

    public function getScoreAttribute(?int $value): int
    {
        return $value ?? $this->currentAssessment?->health_score ?? 0;
    }

    public function getExposicaoAttribute(mixed $value): float
    {
        return (float) ($value ?? $this->currentAssessment?->priority_score ?? 0);
    }

    public function getNivelAttribute(): string
    {
        return Risco::nivel($this->score, app(CompanyContext::class)->current()?->limiares());
    }

    public function getSinaisAttribute(): array
    {
        return $this->currentAssessment?->signals_json['evidence'] ?? [];
    }

    /** Parcelas de todos os sinais, inclusive as menores que o limite dos destaques. */
    public function contribuicoesScore(): array
    {
        $severidades = $this->currentAssessment?->signals_json['severity'] ?? null;

        if (! is_array($severidades) || count($severidades) !== count(Risco::PESOS)) {
            return [];
        }

        $severidades = array_combine(array_keys(Risco::PESOS), $severidades);
        $pesos = app(CompanyContext::class)->current()?->pesos() ?? Risco::PESOS;
        $pontos = Risco::pontos($severidades, $pesos);

        return array_map(function ($chave, $pontos) use ($severidades, $pesos): array {
            $base = round($severidades[$chave] * 100 / count(Risco::PESOS), 1);

            return [
                'rotulo' => Risco::ROTULOS[$chave],
                'intensidade' => $severidades[$chave],
                'peso' => $pesos[$chave],
                'base' => $base,
                'ajuste_prioridade' => round($pontos - $base, 1),
                'pontos' => $pontos,
            ];
        }, array_keys($pontos), array_values($pontos));
    }

    public function resumoScore(): string
    {
        if (! $this->currentAssessment) {
            return 'Sem avaliação: faltam métricas mensais para calcular o score.';
        }

        $principais = collect($this->contribuicoesScore())->sortByDesc('pontos')->take(3)
            ->map(fn ($item) => "{$item['rotulo']} +{$item['pontos']}")->join('; ');

        return "Risco por regras (índice em %): soma ponderada de 8 sinais de até 3 meses recentes. Principais parcelas: {$principais}. Não é probabilidade de cancelamento.";
    }

    public function getSimilaresAttribute(): array
    {
        return $this->currentAssessment?->signals_json['similar'] ?? [];
    }

    public function getHistAttribute(): array
    {
        return $this->metrics()->orderBy('reference_month')->get()->map(fn (CustomerMetric $metric): array => [
            'mes' => $metric->reference_month->format('Y-m'),
            'abertos' => $metric->tickets_opened,
            'criticos' => $metric->tickets_critical,
            'reabertos' => $metric->tickets_reopened,
            'sla' => $metric->sla_percentage === null ? '—' : (float) $metric->sla_percentage,
            'uso' => (float) $metric->platform_usage_percentage,
            'recl' => $metric->formal_complaints,
            'atraso' => $metric->payment_delay_days,
            'reunioes' => $metric->meetings_completed.'/'.$metric->meetings_expected,
        ])->all();
    }

    public function getNpsAttribute(): array
    {
        return $this->npsResponses()->orderBy('reference_month')->get()->map(fn (CustomerNps $response): array => [
            'mes' => $response->reference_month->format('Y-m'),
            'nota' => $response->answered ? (string) $response->score : '—',
        ])->all();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_value' => 'decimal:2',
            'contracted_sla_hours' => 'integer',
            'contract_started_at' => 'date',
            'cancelled_at' => 'date',
        ];
    }
}
