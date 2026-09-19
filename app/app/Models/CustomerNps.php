<?php

namespace App\Models;

use Database\Factories\CustomerNpsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'reference_month', 'answered', 'score', 'classification'])]
class CustomerNps extends Model
{
    /** @use HasFactory<CustomerNpsFactory> */
    use HasFactory;

    protected $table = 'customer_nps';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'answered' => 'boolean',
            'score' => 'integer',
        ];
    }
}
