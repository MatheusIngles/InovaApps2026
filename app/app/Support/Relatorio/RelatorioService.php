<?php

namespace App\Support\Relatorio;

use App\Models\Customer;
use App\Support\Llm\Llm;
use App\Support\Tenancy\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

/**
 * Relatório de pontos críticos em PDF: a IA escreve a análise dos sinais escolhidos pelo usuário
 * (com fallback determinístico se a IA estiver indisponível); o PDF é montado com DomPDF.
 */
class RelatorioService
{
    /**
     * @param  list<int>  $indices  índices dos sinais (em $empresa->sinais) escolhidos pelo usuário
     */
    public static function gerar(Customer $empresa, array $indices, ?string $observacoes): string
    {
        $sinais = collect($empresa->sinais)->only($indices)->values()->all();

        return Pdf::loadView('relatorios.pontos-criticos', [
            'empresa' => $empresa,
            'sinais' => $sinais,
            'observacoes' => $observacoes,
            'analise' => self::analise($empresa, $sinais, $observacoes),
            'geradoEm' => now(),
        ])->output();
    }

    private static function analise(Customer $empresa, array $sinais, ?string $observacoes): string
    {
        $config = app(CompanyContext::class)->current()->chat();

        try {
            if (! $config['enabled']) {
                throw new \RuntimeException('IA desativada para esta empresa.');
            }

            return Llm::responder(self::prompt($empresa, $sinais, $observacoes), [
                ['role' => 'user', 'content' => 'Escreva a análise dos pontos críticos selecionados.'],
            ], $config['ollama_model'])['texto'];
        } catch (Throwable) {
            return self::analiseFallback($empresa, $sinais);
        }
    }

    private static function prompt(Customer $c, array $sinais, ?string $observacoes): string
    {
        $linhas = [
            'Você é um especialista em relacionamento e retenção de clientes de uma empresa de serviços com contratos recorrentes.',
            'Escreva a análise de um relatório de pontos críticos sobre o cliente abaixo, em português do Brasil, em texto corrido (parágrafos separados por linha em branco, sem listas, sem títulos, sem markdown).',
            'Baseie-se SOMENTE nos dados abaixo; cite números como evidência. Se algo não estiver nos dados, não invente.',
            '',
            "EMPRESA: {$c->nome} ({$c->codigo}) — {$c->segmento}, porte {$c->porte}, plano {$c->plano}.",
            'Situação: '.($c->cancelada() ? "CANCELADA em {$c->mes_cancel}" : 'ATIVA').
                " | Score de risco: {$c->score}/100 ({$c->nivel}) | Contrato: ".Customer::brl($c->valor).
                '/mês | Exposição mensal indicativa: '.Customer::brl($c->exposicao),
            '',
            'PONTOS CRÍTICOS SELECIONADOS PARA ESTE RELATÓRIO:',
            ...($sinais ? array_map(fn ($s) => "- {$s['label']} (+{$s['pts']} pts): {$s['texto']} Ação recomendada: {$s['acao']}", $sinais) : ['- nenhum selecionado']),
        ];

        if ($observacoes) {
            $linhas[] = '';
            $linhas[] = "O QUE O USUÁRIO PEDIU PARA DESTACAR NA ANÁLISE: {$observacoes}";
        }

        return implode("\n", $linhas);
    }

    private static function analiseFallback(Customer $c, array $sinais): string
    {
        if (! $sinais) {
            return "Nenhum ponto crítico foi selecionado para {$c->nome}.";
        }

        return "{$c->nome} está no nível {$c->nivel}, com score de risco {$c->score}/100. ".
            collect($sinais)->map(fn ($s) => "{$s['texto']} Ação recomendada: {$s['acao']}")->join(' ');
    }
}
