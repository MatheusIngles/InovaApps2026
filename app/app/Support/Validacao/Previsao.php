<?php

namespace App\Support\Validacao;

/**
 * Previsão simples do próximo mês: reta de mínimos quadrados sobre os últimos meses.
 * Não é probabilidade: mostra para onde a série caminha se o ritmo recente continuar.
 */
class Previsao
{
    /**
     * @param  list<float|int>  $serie  valores mensais em ordem cronológica
     * @return array{ajuste_ultimo: int, valor: int, minimo: int, maximo: int, tendencia: float, pontos: int}|null
     */
    public static function proximoMes(array $serie, int $janela = 6, float $teto = 100): ?array
    {
        $y = array_values(array_slice($serie, -$janela));
        $n = count($y);

        if ($n < 3) {
            return null; // poucos meses para enxergar tendência
        }

        $mx = ($n - 1) / 2;
        $my = array_sum($y) / $n;
        $sxx = 0.0;
        $sxy = 0.0;
        foreach ($y as $i => $v) {
            $sxx += ($i - $mx) ** 2;
            $sxy += ($i - $mx) * ($v - $my);
        }
        $b = $sxx > 0 ? $sxy / $sxx : 0.0; // pontos por mês
        $a = $my - $b * $mx;
        $erro = 0.0;
        foreach ($y as $i => $v) {
            $erro += ($v - ($a + $b * $i)) ** 2;
        }
        $desvio = $n > 2 ? sqrt($erro / ($n - 2)) : 0.0;
        $prox = $a + $b * $n;
        $limitar = fn (float $v): int => (int) round(max(0, min($teto, $v)));

        return ['ajuste_ultimo' => $limitar($a + $b * ($n - 1)), 'valor' => $limitar($prox), 'minimo' => $limitar($prox - $desvio), 'maximo' => $limitar($prox + $desvio), 'tendencia' => round($b, 1), 'pontos' => $n];
    }
}
