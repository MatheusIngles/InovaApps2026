<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPeriod>
 */
class CustomerPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'company_id' => fn (array $attributes): int => Customer::findOrFail($attributes['customer_id'])->company_id,
            'reference_month' => now()->startOfMonth()->toDateString(),
            'monthly_value' => 1000,
        ];
    }
}
