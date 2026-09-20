<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MetricDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricDefinition>
 */
class MetricDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => 'metric_'.fake()->unique()->numerify('#####'),
            'label' => fake()->words(2, true),
            'direction' => 'lower',
            'healthy_value' => 100,
            'critical_value' => 0,
            'weight' => 10,
            'enabled' => true,
        ];
    }
}
