<?php

namespace App\Support;

/**
 * Score de risco de cancelamento (0-100) a partir dos 3 últimos meses de cada cliente.
 * Cada sinal vira uma severidade 0-1 multiplicada pelo seu peso; a soma dos pesos é 100.
 * Mediana (não média) nos 3 meses: ignora piora de um mês só.
 */
class Risco
{
    public const PESOS = ['uso' => 20, 'sla' => 15, 'reinc' => 10, 'recl' => 10, 'atraso' => 10, 'reun' => 12, 'nps' => 15, 'tend' => 8];

    public const ROTULOS = [
        'uso' => 'Uso da plataforma', 'sla' => 'SLA cumprido', 'reinc' => 'Reincidência de chamados',
        'recl' => 'Reclamações formais', 'atraso' => 'Atraso de pagamento', 'reun' => 'Reuniões realizadas',
        'nps' => 'Satisfação (NPS)', 'tend' => 'Tendência de queda',
    ];

    private const ACOES = [
        'uso' => 'Agendar sessão de adoção/treinamento e entender o que parou de ser usado.',
        'sla' => 'Escalar chamados fora do prazo e acordar plano de recuperação de SLA com o cliente.',
        'reinc' => 'Rodar análise de causa raiz dos chamados reabertos com o time técnico.',
        'recl' => 'Ligar proativamente (gestor da conta) e responder as reclamações formais.',
        'atraso' => 'Contato financeiro cordial: entender se é caixa, insatisfação ou processo.',
        'reun' => 'Remarcar reunião executiva e reforçar a cadência de acompanhamento.',
        'nps' => 'Contato de recuperação: ler a última avaliação e retornar ao cliente em 48h.',
        'tend' => 'Revisão de saúde da conta: piora consistente frente ao início do histórico.',
    ];

    public static function acao(string $k): ?string
    {
        return self::ACOES[$k] ?? null;
    }

    public const LIMIARES = ['critico' => 55, 'alto' => 40, 'medio' => 25];

    /**
     * @param  array<int, array<string, mixed>>  $hist  linhas de atendimento_mensal (ordenadas por mês)
     * @param  array<int, array<string, mixed>>  $nps  linhas de pesquisas_nps (ordenadas por mês)
     * @param  array<string, float|int>|null  $pesos  pesos da empresa, na ordem de prioridade (padrão: PESOS)
     * @return array{score:int, nivel:string, sinais:array, sev:array}
     *
     * O score é a média ponderada das severidades: soma(severidade × peso) / soma(pesos) × 100.
     * Normalizar pela soma dos pesos mantém a escala 0-100 quando a empresa usa pesos que não somam 100;
     * soma zero (todos os pesos 0) resulta em score 0 em vez de divisão por zero.
     */
    public static function calcular(array $hist, array $nps, ?array $pesos = null, ?array $limiares = null): array
    {
        $pesos ??= self::PESOS;
        $f = self::sinais($hist, $nps);
        $sev = self::severidades($f);
        $total = array_sum($pesos);
        $pts = self::pontos($sev, $pesos);

        $score = (int) round(array_sum($pts));
        $sinais = [];

        foreach ($pesos as $k => $peso) { // a ordem dos pesos é a prioridade: desempata sinais com a mesma pontuação
            $max = $total > 0 ? round($peso / $total * 100, 1) : 0;

            if ($max > 0 && $pts[$k] >= $max * 0.35) {
                $sinais[] = ['k' => $k, 'label' => self::ROTULOS[$k], 'pts' => $pts[$k], 'max' => $max, 'texto' => self::texto($k, $f), 'acao' => self::ACOES[$k]];
            }
        }
        usort($sinais, fn ($a, $b) => $b['pts'] <=> $a['pts']);

        return ['score' => $score, 'nivel' => self::nivel($score, $limiares), 'sinais' => $sinais, 'sev' => array_values($sev)];
    }

    /** @param array<string, float|int> $severidades @param array<string, float|int> $pesos */
    public static function pontos(array $severidades, array $pesos): array
    {
        $total = array_sum($pesos);
        $pontos = [];

        foreach ($pesos as $chave => $peso) {
            $pontos[$chave] = $total > 0 ? round(($severidades[$chave] ?? 0) * $peso / $total * 100, 1) : 0.0;
        }

        return $pontos;
    }

    /** Limiares padrão calibrados no backtest: cancelados ≈ 58 de score médio, ativos ≈ 20. */
    public static function nivel(int $score, ?array $limiares = null): string
    {
        $l = $limiares ?? self::LIMIARES;

        return $score >= $l['critico'] ? 'Crítico' : ($score >= $l['alto'] ? 'Alto' : ($score >= $l['medio'] ? 'Médio' : 'Baixo'));
    }

    /** Semelhança (0-100) entre dois vetores de severidade. */
    public static function semelhanca(array $a, array $b): int
    {
        $d = sqrt(array_sum(array_map(fn ($x, $y) => ($x - $y) ** 2, $a, $b)));

        return (int) round(100 * (1 - $d / sqrt(count($a))));
    }

    private static function sinais(array $hist, array $nps): array
    {
        $w = array_slice($hist, -3);
        $prev = count($hist) > 3 ? array_slice($hist, 0, -3) : $hist;
        $col = fn (array $rows, string $c, $vazio = 0) => array_map(fn ($r) => (float) ($r[$c] ?? $vazio), $rows);
        $abertos = array_sum($col($w, 'chamados_abertos'));
        $previstas = array_sum($col($w, 'reunioes_previstas'));
        $n = array_slice($nps, -3);
        $resp = array_values(array_filter($n, fn ($r) => (int) $r['respondeu'] === 1));

        return [
            'uso' => self::mediana($col($w, 'uso_plataforma_pct')),
            'sla' => self::mediana($col($w, 'pct_sla_cumprido', 100)), // mês sem chamados = sem violação
            'reinc' => $abertos ? array_sum($col($w, 'chamados_reabertos')) / $abertos : 0,
            'recl' => (int) array_sum($col($w, 'reclamacoes_formais')),
            'atraso' => self::mediana($col($w, 'dias_atraso_pagamento')),
            'reun' => $previstas ? array_sum($col($w, 'reunioes_realizadas')) / $previstas : null,
            'nps' => $resp ? (float) end($resp)['nota_nps'] : null,
            'sem_resp' => count($n) - count($resp),
            'n_pesq' => count($n),
            'tend' => array_sum($col($prev, 'uso_plataforma_pct')) / max(1, count($prev)) - self::mediana($col($w, 'uso_plataforma_pct')),
        ];
    }

    private static function severidades(array $f): array
    {
        $c = fn (float $v) => max(0.0, min(1.0, $v));
        $nota = $f['nps'] === null ? 0 : $c((8 - $f['nps']) / 6);
        $silencio = $f['n_pesq'] ? $f['sem_resp'] / $f['n_pesq'] : 0;

        return [
            'uso' => $c((85 - $f['uso']) / 35),
            'sla' => $c((85 - $f['sla']) / 45),
            'reinc' => $c($f['reinc'] / 0.25),
            'recl' => $c($f['recl'] / 4),
            'atraso' => $c($f['atraso'] / 10),
            'reun' => $f['reun'] === null ? 0 : $c((0.8 - $f['reun']) / 0.6),
            'nps' => 0.5 * $nota + 0.5 * $silencio, // não responder também é comportamento
            'tend' => $c($f['tend'] / 30),
        ];
    }

    private static function texto(string $k, array $f): string
    {
        return match ($k) {
            'uso' => sprintf('Uso da plataforma em %.0f%% (mediana de 3 meses)', $f['uso']),
            'sla' => sprintf('SLA cumprido em %.0f%% (mediana de 3 meses)', $f['sla']),
            'reinc' => sprintf('%.0f%% dos chamados foram reabertos', $f['reinc'] * 100),
            'recl' => "{$f['recl']} reclamação(ões) formal(is) em 3 meses",
            'atraso' => sprintf('%.0f dias de atraso de pagamento (mediana)', $f['atraso']),
            'reun' => $f['reun'] == 0 ? 'Nenhuma reunião realizada' : sprintf('%.0f%% das reuniões realizadas', $f['reun'] * 100),
            'nps' => ($f['nps'] === null ? 'Sem nota de NPS' : 'Última nota de NPS: '.(int) $f['nps'])
                .($f['sem_resp'] ? "; {$f['sem_resp']} de {$f['n_pesq']} pesquisas sem resposta" : ''),
            'tend' => sprintf('Uso caiu %.0f p.p. frente ao início do histórico', $f['tend']),
        };
    }

    private static function mediana(array $v): float
    {
        if (! $v) {
            return 0;
        }
        sort($v);
        $m = intdiv(count($v), 2);

        return count($v) % 2 ? $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }
}
