<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@inova.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('senha123'),
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'demo@inova.com'],
            [
                'name' => 'Usuário Demonstração',
                'password' => Hash::make('senha123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
