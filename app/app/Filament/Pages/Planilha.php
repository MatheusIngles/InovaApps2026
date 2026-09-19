<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** Primeira tela de uma empresa nova: população inicial dos dados. Depois disso, novos meses entram por Configurações. */
class Planilha extends Page
{
    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected string $view = 'filament.pages.planilha';

    /** Só aparece no menu enquanto a empresa ainda não tem dados. */
    public static function shouldRegisterNavigation(): bool
    {
        return ! Customer::exists();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Vamos começar: envie a planilha da sua carteira';
    }

    public function getSubheading(): string
    {
        return 'Ela popula o painel com seus clientes, métricas e risco. Depois, novos meses podem ser acrescentados em Configurações.';
    }
}
