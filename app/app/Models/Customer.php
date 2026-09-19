<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['external_code', 'segment', 'size', 'plan', 'monthly_value', 'contracted_sla_hours', 'contract_started_at', 'status', 'cancelled_at'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

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
