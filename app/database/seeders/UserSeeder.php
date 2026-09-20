<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserSeeder extends Seeder
{
    /** Duas empresas de demonstração: "demo" (com a base do desafio) e "beta" (vazia, para testar o envio de planilha e o tema próprio). */
    public function run(): void
    {
        // "demo" é a Globalsys, dona da base do desafio. A logo (dados/globalsys-logo.jpg) é copiada para o disco público se existir.
        $tema = ['primary' => '#0a9bdc', 'secondary' => '#58595b', 'font' => 'Plus Jakarta Sans'];
        $logo = base_path('../dados/globalsys-logo.jpg');
        if (is_file($logo)) {
            Storage::disk('public')->put('logos/globalsys.jpg', file_get_contents($logo));
            $tema['logo'] = 'logos/globalsys.jpg';
        }
        $demo = Company::updateOrCreate(['slug' => 'demo'], ['name' => 'Globalsys', 'theme' => $tema]);
        $beta = Company::firstOrCreate(['slug' => 'beta'], [
            'name' => 'Beta Serviços',
            'theme' => ['primary' => '#0d9488', 'secondary' => '#115e59', 'font' => 'Inter'],
        ]);

        foreach ([['admin@inova.com', 'Administrador', $demo], ['demo@inova.com', 'Usuário Demonstração', $demo], ['admin@beta.com', 'Administrador Beta', $beta]] as [$email, $nome, $empresa]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $nome, 'company_id' => $empresa->id, 'password' => Hash::make('senha12345senha'), 'email_verified_at' => now(),
            ]);
        }
    }
}
