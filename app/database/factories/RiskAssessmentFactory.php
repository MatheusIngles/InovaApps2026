<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\RiskAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskAssessment>
 */
class RiskAssessmentFactory extends Factory
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
            'risk_probability' => 0.5,
            'priority_score' => 1500,
            'expected_revenue_at_risk' => 1500,
            'confidence' => 'Media',
            'signals_json' => ['sla_below_target' => true],
            'recommended_action_json' => null,
            'model_version' => 'test-v1',
            'calculated_at' => now(),
        ];
    }
}
