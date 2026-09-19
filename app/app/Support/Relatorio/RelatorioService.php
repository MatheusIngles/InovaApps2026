<?php

namespace App\Support\Relatorio;

use App\Models\Customer;
use App\Support\Llm\Llm;
use App\Support\Tenancy\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

/**
 * Relatório de pontos críticos em PDF: a IA escreve um parágrafo de análise para cada sinal
 * escolhido pelo usuário, intercalado com o próprio ponto (com fallback determinístico se a IA
 * estiver indisponível); o PDF é montado com DomPDF, em formato de slide (16:9).
 */
class RelatorioService
{
    /** Delimitador que a IA usa para separar a análise de cada ponto — não aparece no PDF. */
    private const MARCADOR = '/@@\d+@@/';

    /**
     * @param  list<int>  $indices  índices dos sinais (em $empresa->sinais) escolhidos pelo usuário
     */
    public static function gerar(Customer $empresa, array $indices, ?string $observacoes): string
    {
        $sinais = collect($empresa->sinais)->only($indices)->values()->all();
        $analises = self::analises($empresa, $sinais, $observacoes);

        // Slide 16:9 (960 x 540 pt): @page no CSS não é respeitado de forma confiável pelo DomPDF.
        return Pdf::loadView('relatorios.pontos-criticos', [
            'empresa' => $empresa,
            'itens' => collect($sinais)->map(fn ($s, $i) => ['sinal' => $s, 'analise' => $analises[$i]])->all(),
            'observacoes' => $observacoes,
            // só as métricas que a empresa realmente usa (peso > 0), na ordem de prioridade configurada.
            'metricas' => collect($empresa->contribuicoesScore())->filter(fn ($m) => $m['peso'] > 0)->values()->all(),
            'geradoEm' => now(),
        ])->setPaper([0, 0, 960, 540])->output();
    }

    /** @return list<string> um parágrafo de análise por sinal, na mesma ordem/quantidade de $sinais */
    private static function analises(Customer $empresa, array $sinais, ?string $observacoes): array
    {
        if (! $sinais) {
            return [];
        }

        $config = app(CompanyContext::class)->current()->chat();

        try {
            if (! $config['enabled']) {
                throw new \RuntimeException('IA desativada para esta empresa.');
            }

            $texto = Llm::responder(self::prompt($empresa, $sinais, $observacoes), [
                ['role' => 'user', 'content' => 'Escreva a análise de cada ponto crítico, no formato pedido.'],
            ], $config['ollama_model'])['texto'];

            $blocos = array_values(array_filter(array_map('trim', preg_split(self::MARCADOR, $texto))));

            if (count($blocos) === count($sinais)) {
                return $blocos;
            }
        } catch (Throwable) {
            // segue para o fallback abaixo (IA indisponível ou resposta em formato inesperado)
        }

        return self::analisesFallback($sinais);
    }

    private static function prompt(Customer $c, array $sinais, ?string $observacoes): string
    {
        $linhas = [
            'Você é um especialista em relacionamento e retenção de clientes de uma empresa de serviços com contratos recorrentes.',
            'Escreva, em português do Brasil, um parágrafo curto (2 a 4 frases) de análise para CADA ponto crítico listado abaixo, baseado SOMENTE nos dados dele. Cite números como evidência; se algo não estiver nos dados, não invente.',
            'Responda EXATAMENTE nesse formato, um bloco por ponto crítico, na mesma ordem e quantidade da lista (são '.count($sinais).' pontos), sem nada antes do primeiro marcador, sem títulos, sem markdown:',
            '@@1@@',
            '(parágrafo do primeiro ponto)',
            '@@2@@',
            '(parágrafo do segundo ponto, e assim por diante)',
            '',
            "EMPRESA: {$c->nome} ({$c->codigo}) — {$c->segmento}, porte {$c->porte}, plano {$c->plano}.",
            'Situação: '.($c->cancelada() ? "CANCELADA em {$c->mes_cancel}" : 'ATIVA').
                " | Score de risco: {$c->score}/100 ({$c->nivel}) | Contrato: ".Customer::brl($c->valor).
                '/mês | Exposição mensal indicativa: '.Customer::brl($c->exposicao),
            '',
            'PONTOS CRÍTICOS, NA ORDEM QUE DEVEM SER ANALISADOS:',
            ...array_map(fn ($s, $i) => ($i + 1).". {$s['label']} (+{$s['pts']} pts): {$s['texto']} Ação recomendada: {$s['acao']}", $sinais, array_keys($sinais)),
        ];

        if ($observacoes) {
            $linhas[] = '';
            $linhas[] = "O QUE O USUÁRIO PEDIU PARA DESTACAR NA ANÁLISE: {$observacoes}";
        }

        return implode("\n", $linhas);
    }

    /** @return list<string> */
    private static function analisesFallback(array $sinais): array
    {
        return array_map(fn ($s) => "{$s['texto']} Ação recomendada: {$s['acao']}", $sinais);
    }
}
