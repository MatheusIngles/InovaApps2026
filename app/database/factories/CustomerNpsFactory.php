<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerNps;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerNps>
 */
class CustomerNpsFactory extends Factory
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
            'answered' => true,
            'score' => 8,
            'classification' => 'Neutro',
        ];
    }

    public function unanswered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'answered' => false,
            'score' => null,
            'classification' => null,
        ]);
    }
}
