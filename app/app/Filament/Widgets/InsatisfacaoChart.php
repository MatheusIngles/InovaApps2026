<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Support\Tenancy\CompanyContext;
use App\Support\Validacao\Backtest;
use App\Support\Validacao\Previsao;
use Filament\Widgets\ChartWidget;

/** Insatisfação (índice de risco) de uma empresa mês a mês, com a previsão do próximo mês pela tendência recente. */
class InsatisfacaoChart extends ChartWidget
{
    protected static bool $isDiscovered = false; // só aparece na página da empresa, não no painel

    public ?string $codigo = null;

    protected ?string $heading = 'Insatisfação mês a mês e previsão';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    /** @return array{meses: list<string>, scores: list<int>, previsao: ?array, cancelada: bool} */
    public static function serie(Customer $cliente): array
    {
        $cliente->loadMissing(['metrics' => fn ($q) => $q->orderBy('reference_month'), 'npsResponses' => fn ($q) => $q->orderBy('reference_month')]);
        $meses = Backtest::mesesDoCliente($cliente, app(CompanyContext::class)->current()->pesos());
        $scores = array_column($meses, 'score');
        $cancelada = $cliente->status === 'Cancelado';

        return ['meses' => array_column($meses, 'mes'), 'scores' => $scores, 'previsao' => $cancelada ? null : Previsao::proximoMes($scores), 'cancelada' => $cancelada];
    }

    private function cliente(): Customer
    {
        return Customer::where('external_code', $this->codigo)->firstOrFail();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $s = self::serie($this->cliente());
        $labels = $s['meses'];
        $real = $s['scores'];
        $prev = array_fill(0, count($real), null);

        if ($s['previsao'] && $real) {
            $prev[count($real) - 1] = end($real); // a linha tracejada parte do último ponto real
            $labels[] = date('Y-m', strtotime(end($labels).'-01 +1 month'));
            $real[] = null;
            $prev[] = $s['previsao']['valor'];
        }

        $primaria = app(CompanyContext::class)->current()->tema()['primary'];

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Insatisfação (risco %)', 'data' => $real, 'borderColor' => $primaria, 'backgroundColor' => $primaria, 'tension' => .3, 'pointRadius' => 3, 'spanGaps' => false],
                ['label' => 'Previsão do próximo mês', 'data' => $prev, 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b', 'borderDash' => [6, 5], 'pointRadius' => 5, 'tension' => 0, 'spanGaps' => true],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['position' => 'bottom']]];
    }
}
