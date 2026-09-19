<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_code' => fake()->unique()->bothify('C###'),
            'segment' => fake()->randomElement(['Saude', 'Logistica', 'Varejo']),
            'size' => 'Pequeno',
            'plan' => 'Essencial',
            'monthly_value' => fake()->numberBetween(1000, 20000),
            'contracted_sla_hours' => 24,
            'contract_started_at' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'status' => 'Ativo',
            'cancelled_at' => null,
        ];
    }
}
