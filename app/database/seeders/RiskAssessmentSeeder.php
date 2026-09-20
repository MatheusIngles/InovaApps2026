<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\RiskService;
use Illuminate\Database\Seeder;

/** Recalcula o risco de todas as empresas com os pesos/limiares configurados por cada uma. */
class RiskAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        Company::each(fn (Company $company) => RiskService::recalcular($company));
    }
}
