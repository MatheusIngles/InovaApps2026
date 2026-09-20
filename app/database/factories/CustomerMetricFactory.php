<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerMetric>
 */
class CustomerMetricFactory extends Factory
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
            'reference_month' => now()->startOfMonth()->toDateString(),
            'tickets_opened' => 5,
            'tickets_critical' => 1,
            'tickets_reopened' => 0,
            'tickets_within_sla' => 4,
            'sla_percentage' => 80,
            'avg_resolution_hours' => 12,
            'formal_complaints' => 0,
            'platform_usage_percentage' => 85,
            'payment_delay_days' => 0,
            'meetings_expected' => 1,
            'meetings_completed' => 1,
        ];
    }
}
