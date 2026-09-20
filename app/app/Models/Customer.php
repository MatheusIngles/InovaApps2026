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
            ->select('customers.*', 'assessment.health_score as score', 'assessment.exposure_indicator as exposicao')
            ->with('currentAssessment');
    }

    public static function ordenar(Builder $query): Builder
    {
        // Canceladas por último. Duas camadas entre as ativas: primeiro quem já está em alerta (nível Médio ou acima),
        // depois os demais; em cada camada, risco × (risco + K) × valor do contrato, com K configurável (Configurações).
        // Assim um contrato grande desempata entre clientes que precisam de atenção, mas nunca põe um cliente sem alerta
        // na frente de um em alerta.
        $company = app(CompanyContext::class)->current();
        $k = $company?->prioridadeK() ?? Company::PRIORIDADE_PADRAO;
        $emAlerta = $company?->limiares()['medio'] ?? Risco::LIMIARES['medio'];

        return $query->orderByRaw("customers.status = 'Cancelado'")
            ->orderByRaw('(assessment.health_score >= ?) DESC', [$emAlerta])
            ->orderByRaw(self::RANKING_SQL.' DESC', [$k]);
    }

    /** Valor de ordenação da fila (único lugar da fórmula): atenção × (atenção + K) × valor mensal do contrato. */
    public const RANKING_SQL = 'assessment.health_score * (assessment.health_score + ?) * customers.monthly_value';

    public static function ranking(int $atencao, float $valorMensal, int $k): float
    {
        return $atencao * ($atencao + $k) * $valorMensal;
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
        return $this->cancelada() ? 'Cancelado' : $this->nivel;
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
        return (float) ($value ?? $this->currentAssessment?->exposure_indicator ?? 0);
    }

    public function getNivelAttribute(): string
    {
        return Risco::nivel($this->score, app(CompanyContext::class)->current()?->limiares());
    }

    public function getSinaisAttribute(): array
    {
        return $this->currentAssessment?->signals_json['evidence'] ?? [];
    }

    /** Por que o cliente está na fila: os $quantos principais sinais, em frases curtas. */
    public function porQue(int $quantos = 2): string
    {
        $textos = array_map(fn (array $s): string => $s['texto'], array_slice($this->sinais, 0, $quantos));

        return $textos ? implode('; ', $textos) : 'Sem sinais relevantes';
    }

    /** O que fazer e com que urgência (nulo para cancelados): urgência do nível + ação do principal sinal. */
    public function proximoPasso(): ?string
    {
        if ($this->cancelada()) {
            return null;
        }
        $acao = $this->sinais[0]['acao'] ?? 'Manter o acompanhamento normal.';

        return (Risco::PRAZOS[$this->nivel] ?? 'Acompanhar').' · '.$acao;
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
            return 'Sem avaliação: faltam métricas mensais para calcular a atenção.';
        }

        $principais = collect($this->contribuicoesScore())->sortByDesc('pontos')->take(3)
            ->map(fn ($item) => "{$item['rotulo']} +{$item['pontos']}")->join('; ');

        return "Atenção por regras (índice de 0 a 100): soma ponderada de 8 sinais de até 3 meses recentes. Principais parcelas: {$principais}. Não é probabilidade de cancelamento.";
    }

    public function getSimilaresAttribute(): array
    {
        return $this->currentAssessment?->signals_json['similar'] ?? [];
    }

    /** Meses até a saída (o cálculo, o backtest e o gráfico ignoram os posteriores ao cancelamento). */
    private function antesDaSaida(HasMany $query): HasMany
    {
        return $this->cancelled_at ? $query->where('reference_month', '<', $this->cancelled_at) : $query;
    }

    public function getHistAttribute(): array
    {
        return $this->antesDaSaida($this->metrics()->orderBy('reference_month'))->get()->map(fn (CustomerMetric $metric): array => [
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
        return $this->antesDaSaida($this->npsResponses()->orderBy('reference_month'))->get()->map(fn (CustomerNps $response): array => [
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
