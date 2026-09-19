<?php

namespace App\Models;

use App\Support\Risco;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tenant. Guarda tema, pesos das métricas, limiares dos níveis, configurações do chat e mapeamento de colunas. */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $guarded = [];

    public const TEMA_PADRAO = ['primary' => '#2563eb', 'secondary' => '#1d4ed8', 'font' => 'Plus Jakarta Sans', 'logo' => null, 'brand' => 'Seer'];

    /** K da fila: risco × (risco + K) × valor. Menor reforça o risco; maior aproxima risco × valor. */
    public const PRIORIDADE_PADRAO = 50;

    public const CHAT_PADRAO = ['enabled' => true, 'ollama_model' => null, 'instrucoes' => null];

    protected function casts(): array
    {
        return [
            'theme' => 'array', 'metric_weights' => 'array', 'level_thresholds' => 'array',
            'chat_settings' => 'array', 'column_mapping' => 'array', 'imported_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->withoutGlobalScopes();
    }

    /** Pesos por métrica, na ordem de prioridade escolhida; métricas não salvas entram ao final com o peso padrão. */
    public function pesos(): array
    {
        $out = [];

        foreach ($this->metric_weights ?? [] as $item) {
            if (isset(Risco::PESOS[$item['k'] ?? null])) {
                $out[$item['k']] = max(0.0, (float) $item['peso']);
            }
        }

        return $out + Risco::PESOS;
    }

    /** @return array{critico: int, alto: int, medio: int} */
    public function limiares(): array
    {
        return array_map('intval', array_merge(Risco::LIMIARES, $this->level_thresholds ?? []));
    }

    public function prioridadeK(): int
    {
        return $this->priority_balance ?? self::PRIORIDADE_PADRAO;
    }

    public function tema(): array
    {
        return array_merge(self::TEMA_PADRAO, array_filter($this->theme ?? [], fn ($v) => $v !== null && $v !== ''));
    }

    public function chat(): array
    {
        return array_merge(self::CHAT_PADRAO, $this->chat_settings ?? []);
    }
}
