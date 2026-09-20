<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\MetricDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'code', 'label', 'description', 'value_type', 'direction', 'healthy_value', 'critical_value', 'weight', 'enabled'])]
class MetricDefinition extends Model
{
    /** @use HasFactory<MetricDefinitionFactory> */
    use BelongsToCompany, HasFactory;

    public const TYPES = [
        'decimal' => 'Número decimal',
        'integer' => 'Número inteiro',
        'percentage' => 'Percentual',
        'currency' => 'Valor monetário',
        'binary' => 'Binário (0 ou 1)',
        'grade' => 'Nota (0 a 10)',
        'date' => 'Data (fora do cálculo da atenção)',
        'text' => 'Texto (fora do cálculo da atenção)',
    ];

    /** Tipos que guardam o valor como texto e ficam fora do cálculo da atenção. */
    public const SEM_SCORE = ['text', 'date'];

    public static function semScore(?string $type): bool
    {
        return in_array($type, self::SEM_SCORE, true);
    }

    public function values(): HasMany
    {
        return $this->hasMany(MetricValue::class);
    }

    protected function casts(): array
    {
        return [
            'healthy_value' => 'decimal:4',
            'critical_value' => 'decimal:4',
            'weight' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }
}
