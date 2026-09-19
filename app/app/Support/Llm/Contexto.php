<?php

namespace App\Support\Llm;

use App\Models\Customer;
use App\Support\Risco;
use App\Support\Tenancy\CompanyContext;

/** Monta o prompt de sistema injetando o contexto específico da empresa (ou da carteira). */
class Contexto
{
    public static function sistema(?Customer $empresa): string
    {
        $company = app(CompanyContext::class)->current();
        $prompt = str_replace('{{contexto}}', $empresa ? self::empresa($empresa) : self::carteira(), config('llm.prompt_base'));

        // contexto isolado da empresa (tenant) + instruções próprias dela
        return $company ? "Você atende exclusivamente a empresa \"{$company->name}\"; use somente os dados dela.
".$prompt.'

'.self::prioridades().'
Use a ordem e os pesos dessas métricas para explicar o score e priorizar as recomendações. Métricas desativadas não contribuem para o score. Não confunda o peso configurado com a pontuação efetiva do cliente; para explicar o risco atual, cite os sinais e os pontos da avaliação.
'.($company->chat()['instrucoes'] ? '

INSTRUÇÕES DA EMPRESA:
'.$company->chat()['instrucoes'] : '') : $prompt;
    }

    public static function prioridades(): string
    {
        $company = app(CompanyContext::class)->current();

        if ($company === null) {
            return 'Prioridades de métricas indisponíveis.';
        }

        $pesos = $company->pesos();
        $total = array_sum($pesos);
        $linhas = ['PRIORIDADES DAS MÉTRICAS DESTA EMPRESA (ordem configurada; participação no score):'];
        $posicao = 0;

        foreach ($pesos as $chave => $peso) {
            $rotulo = Risco::ROTULOS[$chave];

            if ($peso <= 0) {
                $linhas[] = "- {$rotulo}: desativada (peso 0)";

                continue;
            }

            $posicao++;
            $participacao = $total > 0 ? round($peso / $total * 100, 1) : 0;
            $linhas[] = "{$posicao}. {$rotulo}: peso {$peso}; participação máxima {$participacao} pontos em 100";
        }

        $limiares = $company->limiares();
        $linhas[] = "Limites de nível: médio a partir de {$limiares['medio']}, alto a partir de {$limiares['alto']}, crítico a partir de {$limiares['critico']}.";

        return implode("\n", $linhas);
    }

    public static function empresa(Customer $c): string
    {
        $linhas = [
            "EMPRESA EM FOCO: {$c->nome} (código {$c->codigo})",
            "Segmento: {$c->segmento} | Porte: {$c->porte} | Plano: {$c->plano} | Cliente desde: {$c->inicio}",
            'Contrato: '.Customer::brl($c->valor)."/mês, SLA contratado {$c->sla_h}h",
            'Situação: '.($c->cancelada() ? "CANCELADA em {$c->mes_cancel}" : 'ATIVA')." | Score de risco: {$c->score}/100 ({$c->nivel}) | Exposição mensal: ".Customer::brl($c->exposicao),
            '',
            'SINAIS DE ALERTA (últimos 3 meses):',
            ...($c->sinais ? array_map(fn ($s) => "- {$s['label']}: {$s['texto']} → ação sugerida: {$s['acao']}", $c->sinais) : ['- nenhum sinal relevante']),
            '',
            'EMPRESAS QUE CANCELARAM EM ESTADO SIMILAR:',
            ...array_map(fn ($s) => "- {$s['nome']}: {$s['sim']}% de semelhança, saiu em {$s['mes_cancel']}", $c->similares),
            '',
            'NPS (mês: nota): '.implode(', ', array_map(fn ($n) => "{$n['mes']}: {$n['nota']}", array_slice($c->nps, -8))),
            '',
            'HISTÓRICO MENSAL RECENTE (mês | chamados | reabertos | SLA% | uso% | reclamações | atraso(d) | reuniões):',
            ...array_map(fn ($h) => implode(' | ', [$h['mes'], $h['abertos'], $h['reabertos'], $h['sla'], $h['uso'], $h['recl'], $h['atraso'], $h['reunioes']]), array_slice($c->hist, -6)),
        ];

        return implode("\n", $linhas);
    }

    public static function carteira(): string
    {
        $ativas = Customer::ativas();
        $linhas = [
            'CONTEXTO: visão geral da carteira (sem empresa específica em foco).',
            "Clientes ativos: {$ativas->count()} | Receita mensal ativa: ".Customer::brl($ativas->sum('valor')).' | Exposição mensal total: '.Customer::brl($ativas->sum('exposicao')),
            'FILA DE ATENDIMENTO (top 10 por prioridade = risco × valor do contrato):',
            ...$ativas->take(10)->map(fn ($c, $i) => ($i + 1).". {$c->nome} ({$c->codigo}) — {$c->nivel}, score {$c->score}, ".Customer::brl($c->valor).'/mês, principal motivo: '.($c->sinais[0]['label'] ?? 'sem sinal forte'))->all(),
        ];

        return implode("\n", $linhas);
    }
}
