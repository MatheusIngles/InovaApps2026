<?php

namespace App\Filament\Widgets;

use App\Support\Tenancy\CompanyContext;
use Filament\Widgets\Widget;

/** "O alerta funciona?": fica logo abaixo da comparação exploratória do risco (ordem 5) e acima da fila. */
class ResumoEvidenciaWidget extends Widget
{
    public static function canView(): bool
    {
        return app(CompanyContext::class)->current()?->hasLegacyMetrics() ?? false;
    }

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.resumo-evidencia';
}
