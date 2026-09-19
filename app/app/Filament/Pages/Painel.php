<?php

namespace App\Filament\Pages;

use App\Jobs\GerarRelatorioCarteiraJob;
use App\Support\Tenancy\CompanyContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/** Dashboard em /painel: visão geral e análise por segmento (abas); a evidência completa vai no relatório em PDF. */
class Painel extends Dashboard
{
    protected static string $routePath = '/painel';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?int $navigationSort = 2;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('relatorioCarteira')->label('Gerar relatório de evidências')->icon('heroicon-o-document-arrow-down')
                ->action(function (): void {
                    GerarRelatorioCarteiraJob::dispatch(app(CompanyContext::class)->id(), auth()->id(), (string) Str::uuid());
                    Notification::make()->title('Relatório em preparação')
                        ->body('Você receberá uma notificação com o link do PDF quando ele estiver pronto.')->success()->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Painel')->tabs([
                Tab::make('Visão geral')->schema([$this->getWidgetsContentComponent()]),
                Tab::make('Por segmento')->schema([View::make('filament.components.painel-segmentos')]),
            ])->persistTabInQueryString('aba')->columnSpanFull(),
        ]);
    }
}
