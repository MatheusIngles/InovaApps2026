<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Assistente extends Page
{
    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected string $view = 'filament.pages.assistente';

    protected static ?string $slug = 'assistente';

    public function getSubheading(): string
    {
        return 'Responde localmente com base nos dados de toda a carteira. Cite o código (ex.: C012) para falar de uma empresa.';
    }
}
