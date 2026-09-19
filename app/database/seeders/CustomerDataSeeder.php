<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\Import\ImportService;
use App\Support\Import\PlanilhaReader;
use Illuminate\Database\Seeder;

/** Empresa "demo": importa a base do desafio pelo mesmo motor de importação usado na tela de planilha. */
class CustomerDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(['slug' => 'demo'], ['name' => 'Empresa Demo']);
        $tabela = PlanilhaReader::ler(database_path('data/INOVAAPPS_base_de_dados.xlsx'), 'xlsx');

        // a planilha do desafio já usa os nomes canônicos de coluna
        ImportService::importar($company, $tabela['linhas'], ImportService::sugerirMapeamento($tabela['cabecalhos']));
    }
}
