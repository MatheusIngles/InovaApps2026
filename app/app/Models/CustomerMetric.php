<?php

namespace App\Models;

use Database\Factories\CustomerMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'reference_month', 'tickets_opened', 'tickets_critical', 'tickets_reopened', 'tickets_within_sla', 'sla_percentage', 'avg_resolution_hours', 'formal_complaints', 'platform_usage_percentage', 'payment_delay_days', 'meetings_expected', 'meetings_completed'])]
class CustomerMetric extends Model
{
    /** @use HasFactory<CustomerMetricFactory> */
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
            'tickets_opened' => 'integer',
            'tickets_critical' => 'integer',
            'tickets_reopened' => 'integer',
            'tickets_within_sla' => 'integer',
            'sla_percentage' => 'decimal:2',
            'avg_resolution_hours' => 'decimal:2',
            'formal_complaints' => 'integer',
            'platform_usage_percentage' => 'decimal:2',
            'payment_delay_days' => 'integer',
            'meetings_expected' => 'integer',
            'meetings_completed' => 'integer',
        ];
    }
}
