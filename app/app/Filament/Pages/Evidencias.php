<?php

namespace App\Filament\Pages;

use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** Evidências: o que os cancelamentos passados ensinam (antecedência do sinal, alarme falso e peso de cada variável). */
class Evidencias extends Page
{
    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.evidencias';

    protected static ?string $title = 'Evidências';

    /** Limiar de alerta analisado: medio, alto ou critico (os cortes de nível da empresa). */
    #[Url(as: 'nivel')]
    public string $nivel = 'alto';

    public function updatedNivel(): void
    {
        $this->nivel = in_array($this->nivel, ['medio', 'alto', 'critico'], true) ? $this->nivel : 'alto';
    }

    public function getSubheading(): string
    {
        return 'O que o histórico dos cancelamentos ensina: com quanta antecedência o alerta aparece, quantos alarmes são falsos e o que pesa em cada variável.';
    }

    protected function getViewData(): array
    {
        $company = app(CompanyContext::class)->current();
        $r = Backtest::resumo($company);

        return $r + ['nivel' => $this->nivel, 'atual' => $r['limiares'][$this->nivel], 'linhas' => $r['cancelamentos'][$this->nivel], 'niveis' => $company->limiares()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aplicar')->label('Ordenar métricas pela evidência')->color('gray')->requiresConfirmation()
                ->modalDescription('Reordena a prioridade das métricas em Configurações pelo quanto cada uma separa cancelados de retidos e desliga as que não separam. O risco de todos os clientes é recalculado. Dá para voltar em Configurações.')
                ->action(function () {
                    CompanyConfig::aplicarOrdemDosDados(app(CompanyContext::class)->current());
                    Notification::make()->title('Prioridade das métricas atualizada pelos dados')->success()->send();
                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
