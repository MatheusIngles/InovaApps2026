<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Assistente extends Page
{
    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected string $view = 'filament.pages.assistente';

    protected static ?string $slug = 'assistente';

    /** Tela cheia de chat: sem cabeçalho de página. */
    public function getHeading(): string|Htmlable
    {
        return '';
    }
}
