<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\Metricas\SinaisExtras;
use App\Support\RiskService;
use App\Support\Validacao\Backtest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seer:ativar-sinais-extras')]
#[Description('Faz chamados críticos, tempo de resolução e volume de chamados entrarem na atenção das empresas que têm esses dados')]
class AtivarSinaisExtras extends Command
{
    public function handle(): int
    {
        Company::each(function (Company $company): void {
            $ativadas = SinaisExtras::ativar($company);
            if ($ativadas > 0) {
                Backtest::invalidar($company);
                RiskService::recalcular($company);
            }
            $this->line("{$company->name}: {$ativadas} métrica(s) ativada(s).");
        });

        return self::SUCCESS;
    }
}
