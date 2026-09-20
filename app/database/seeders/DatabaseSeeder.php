<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Cria contas de demonstração com senha conhecida: nunca em produção.
        if (app()->isProduction()) {
            $this->command?->error('Seeder de demonstração bloqueado em produção.');

            return;
        }

        $this->call([
            UserSeeder::class,
            CustomerDataSeeder::class,
            RiskAssessmentSeeder::class,
        ]);
    }
}
