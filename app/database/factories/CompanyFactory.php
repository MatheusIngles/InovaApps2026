<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->unique()->company();

        return ['name' => $nome, 'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 9999)];
    }
}
