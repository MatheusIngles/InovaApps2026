<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricValue>
 */
class MetricValueFactory extends Factory
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
            'metric_definition_id' => fn (array $attributes): int => MetricDefinition::factory()->for(Company::findOrFail($attributes['company_id']))->create()->id,
            'reference_month' => now()->startOfMonth()->toDateString(),
            'value' => fake()->numberBetween(0, 100),
        ];
    }
}
