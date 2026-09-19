<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Support\Facades\Storage;

/** Aplica o tema da empresa ao painel: cor primária (paleta do Filament), fonte, nome e logo. A cor secundária vira variável CSS (views/tema). */
class Tema
{
    public static function aplicar(Company $company): void
    {
        $t = $company->tema();
        $painel = Filament::getCurrentOrDefaultPanel();

        FilamentColor::register(['primary' => Color::hex($t['primary'])]);
        $painel->brandName($company->name)->font($t['font']);

        if ($t['logo']) {
            $painel->brandLogo(Storage::disk('public')->url($t['logo']))->brandLogoHeight('2rem');
        }
    }
}
