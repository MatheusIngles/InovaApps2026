<?php

namespace App\Filament\Pages;

use App\Jobs\GerarRelatorioCarteiraJob;
use App\Support\Tenancy\CompanyContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
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
                    GerarRelatorioCarteiraJob::dispatchAfterResponse(app(CompanyContext::class)->id(), auth()->id(), (string) Str::uuid());
                    Notification::make()->title('Relatório em preparação')
                        ->body('Você receberá uma notificação com o link do PDF quando ele estiver pronto.')->success()->send();
                }),
        ];
    }

    /** Visão geral (indicadores e fila), gráficos e, para quem tem os sinais padrão, a análise por segmento. */
    public function content(Schema $schema): Schema
    {
        $abas = [
            Tab::make('Visão geral')->icon('heroicon-o-squares-2x2')->schema([$this->grade(graficos: false)]),
            Tab::make('Gráficos')->icon('heroicon-o-chart-bar')->schema([$this->grade(graficos: true)]),
        ];
        if (app(CompanyContext::class)->current()->hasLegacyMetrics()) {
            $abas[] = Tab::make('Por segmento')->schema([View::make('filament.components.painel-segmentos')]);
        }

        return $schema->components([Tabs::make('Painel')->tabs($abas)->persistTabInQueryString('aba')->columnSpanFull()]);
    }

    /** Os widgets do painel, separados entre gráficos e o restante (indicadores, fila, evidências). */
    private function grade(bool $graficos): Grid
    {
        $widgets = array_values(array_filter(
            $this->getWidgets(),
            fn ($widget): bool => is_subclass_of(is_string($widget) ? $widget : $widget->widget, ChartWidget::class) === $graficos,
        ));

        return Grid::make($this->getColumns())->schema(fn (): array => $this->getWidgetsSchemaComponents($widgets));
    }
}
