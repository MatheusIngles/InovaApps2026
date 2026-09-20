<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\MetricValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'customer_id', 'metric_definition_id', 'reference_month', 'value', 'text_value'])]
class MetricValue extends Model
{
    /** @use HasFactory<MetricValueFactory> */
    use BelongsToCompany, HasFactory;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(MetricDefinition::class, 'metric_definition_id');
    }

    protected function casts(): array
    {
        return ['reference_month' => 'date', 'value' => 'decimal:4'];
    }
}
