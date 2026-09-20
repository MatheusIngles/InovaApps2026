<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CustomerPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'customer_id', 'reference_month', 'monthly_value'])]
class CustomerPeriod extends Model
{
    /** @use HasFactory<CustomerPeriodFactory> */
    use BelongsToCompany, HasFactory;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return ['reference_month' => 'date', 'monthly_value' => 'decimal:2'];
    }
}
