<?php

namespace App\Models;

use Database\Factories\RiskAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'reference_month', 'health_score', 'risk_probability', 'exposure_indicator', 'expected_revenue_at_risk', 'confidence', 'signals_json', 'recommended_action_json', 'model_version', 'calculated_at'])]
class RiskAssessment extends Model
{
    /** @use HasFactory<RiskAssessmentFactory> */
    use HasFactory;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'health_score' => 'integer',
            'risk_probability' => 'decimal:6',
            'exposure_indicator' => 'decimal:2',
            'expected_revenue_at_risk' => 'decimal:2',
            'signals_json' => 'array',
            'recommended_action_json' => 'array',
            'calculated_at' => 'datetime',
        ];
    }
}
