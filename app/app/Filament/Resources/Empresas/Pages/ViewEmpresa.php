<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEmpresa extends ViewRecord
{
    protected static string $resource = EmpresaResource::class;

    public function getSubheading(): string
    {
        $e = $this->record;

        return "{$e->codigo} · {$e->segmento} · porte {$e->porte} · cliente desde ".date('m/Y', strtotime($e->inicio))
            .($e->cancelada() ? " · cancelou em {$e->mes_cancel}" : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('chat')
                ->label('Chat da empresa')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->slideOver()
                ->modalHeading(fn () => "Chat · {$this->record->nome}")
                ->modalContent(fn () => view('filament.chat-modal', ['codigo' => $this->record->codigo]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }
}
