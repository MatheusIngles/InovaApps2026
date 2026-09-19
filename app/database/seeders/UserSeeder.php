<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /** Duas empresas de demonstração: "demo" (com a base do desafio) e "beta" (vazia, para testar o envio de planilha e o tema próprio). */
    public function run(): void
    {
        $demo = Company::firstOrCreate(['slug' => 'demo'], ['name' => 'Empresa Demo']);
        $beta = Company::firstOrCreate(['slug' => 'beta'], [
            'name' => 'Beta Serviços',
            'theme' => ['primary' => '#0d9488', 'secondary' => '#115e59', 'font' => 'Inter'],
        ]);

        foreach ([['admin@inova.com', 'Administrador', $demo], ['demo@inova.com', 'Usuário Demonstração', $demo], ['admin@beta.com', 'Administrador Beta', $beta]] as [$email, $nome, $empresa]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $nome, 'company_id' => $empresa->id, 'password' => Hash::make('senha123'), 'email_verified_at' => now(),
            ]);
        }
    }
}
