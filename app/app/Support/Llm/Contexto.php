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

'.self::comoLer().'
Use a ordem e os pesos dessas métricas para explicar a atenção (índice de 0 a 100) e priorizar as recomendações. Métricas desativadas não contribuem para a atenção. Não confunda o peso configurado com a pontuação efetiva do cliente; para explicar o risco atual, cite os sinais e os pontos da avaliação.
'.($company->chat()['instrucoes'] ? '

INSTRUÇÕES DA EMPRESA:
'.$company->chat()['instrucoes'] : '') : $prompt;
    }

    /** Regras de leitura que a IA precisa saber para não inventar: o que é a atenção, os níveis e a ordem da fila. */
    public static function comoLer(): string
    {
        $company = app(CompanyContext::class)->current();
        $l = $company?->limiares() ?? Risco::LIMIARES;
        $k = $company?->prioridadeK() ?? Company::PRIORIDADE_PADRAO;
        $dia = Risco::CONTATOS_POR_DIA;

        return implode('
', [
            'COMO LER O SISTEMA (use estas regras ao responder):',
            '- A atenção é um índice de 0 a 100 dos sinais de alerta (média ponderada da gravidade de 8 sinais). NÃO é a probabilidade nem a chance de o cliente cancelar; se perguntarem, diga isso com clareza.',
            "- Níveis pela atenção: Baixo (abaixo de {$l['medio']}): sem alerta, acompanhamento normal; Médio ({$l['medio']} a ".($l['alto'] - 1)."); Alto ({$l['alto']} a ".($l['critico'] - 1)."); Crítico ({$l['critico']} ou mais). Médio, Alto e Crítico estão em alerta.",
            "- Ordem da fila de atendimento: primeiro os clientes em alerta (Médio ou acima) e depois os demais; dentro de cada grupo, por atenção × (atenção + K) × valor mensal do contrato, com K = {$k}. Um contrato grande desempata, mas nunca põe um cliente sem alerta na frente de um em alerta. Por isso o primeiro da fila não é necessariamente o de maior atenção: cite a posição da fila, e não só o maior número.",
            'Prazo de contato pela posição na fila: posições 1 a '.$dia.' contato hoje; até '.($dia * 3).' em até 3 dias; até '.($dia * 5).' nesta semana; nível Baixo ou além disso, acompanhamento normal.',
        ]);
    }

    public static function prioridades(): string
    {
        $company = app(CompanyContext::class)->current();

        if ($company === null) {
            return 'Prioridades de métricas indisponíveis.';
        }

        $pesos = $company->hasLegacyMetrics() ? $company->pesos() : [];
        $total = array_sum($pesos);
        $linhas = ['PRIORIDADES DAS MÉTRICAS DESTA EMPRESA (ordem configurada; participação na atenção):'];
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

        foreach ($company->metricDefinitions()->orderBy('code')->get() as $definition) {
            $state = ! $definition->enabled ? 'desativada' : ($definition->value_type === 'text' ? 'textual; fora do score' : "peso {$definition->weight}; piora quando ".($definition->direction === 'higher' ? 'aumenta' : 'diminui')."; saudável {$definition->healthy_value}; crítico {$definition->critical_value}");
            $linhas[] = "- {$definition->label} ({$definition->code}, {$definition->value_type}): {$definition->description}; {$state}.";
        }

        $limiares = $company->limiares();
        $linhas[] = "Limites de nível: médio a partir de {$limiares['medio']}, alto a partir de {$limiares['alto']}, crítico a partir de {$limiares['critico']}.";

        return implode("\n", $linhas);
    }

    public static function empresa(Customer $c): string
    {
        $company = app(CompanyContext::class)->current();
        if (! $c->metrics()->exists()) {
            $definitions = $company->metricDefinitions()->orderBy('code')->get();
            $values = $c->metricValues()->get()->keyBy(fn ($value) => $value->reference_month->format('Y-m').':'.$value->metric_definition_id);
            $months = $c->periods()->orderByDesc('reference_month')->limit(6)->pluck('reference_month');
            $history = $months->map(function ($date) use ($definitions, $values): string {
                $month = substr($date, 0, 7);
                $cells = $definitions->map(function ($definition) use ($month, $values): string {
                    $observed = $values->get($month.':'.$definition->id);
                    $value = $observed ? ($definition->value_type === 'text' ? $observed->text_value : $observed->value) : 'NULL';

                    return "{$definition->code}={$value}";
                });

                return "- {$month}: ".$cells->implode('; ');
            });

            return implode("\n", [
                "CLIENTE EM FOCO: {$c->nome} (código {$c->codigo})",
                "Segmento: {$c->segmento} | Porte: {$c->porte} | Plano: {$c->plano} | Contrato: ".Customer::brl($c->valor).'/mês',
                'Atenção: '.($c->currentAssessment ? "{$c->score}/100 ({$c->nivel})" : 'sem avaliação numérica').'; valores ausentes são NULL, não zero.',
                'SINAIS RECENTES:',
                ...($c->sinais ? array_map(fn ($signal) => "- {$signal['label']}: {$signal['texto']}", $c->sinais) : ['- nenhum sinal relevante']),
                'HISTÓRICO MENSAL DE MÉTRICAS:',
                ...$history->all(),
            ]);
        }

        $linhas = [
            "CLIENTE EM FOCO: {$c->nome} (código {$c->codigo})",
            "Segmento: {$c->segmento} | Porte: {$c->porte} | Plano: {$c->plano} | Cliente desde: {$c->inicio}",
            'Contrato: '.Customer::brl($c->valor)."/mês, SLA contratado {$c->sla_h}h",
            'Situação: '.($c->cancelada() ? "CANCELADA em {$c->mes_cancel}" : 'ATIVA')." | Atenção: {$c->score}/100 ({$c->nivel}) | Exposição mensal: ".Customer::brl($c->exposicao),
            '',
            'SINAIS DE ALERTA (últimos 3 meses):',
            ...($c->sinais ? array_map(fn ($s) => "- {$s['label']}: {$s['texto']} → ação sugerida: {$s['acao']}", $c->sinais) : ['- nenhum sinal relevante']),
            '',
            'CLIENTES QUE CANCELARAM EM ESTADO SIMILAR:',
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
        if (! app(CompanyContext::class)->current()->hasLegacyMetrics()) {
            return implode("\n", [
                'CONTEXTO: visão geral da carteira (sem cliente específico em foco).',
                "Clientes ativos: {$ativas->count()} | Receita mensal ativa: ".Customer::brl($ativas->sum('valor')).' | Métricas configuradas: '.app(CompanyContext::class)->current()->metricDefinitions()->count(),
                'FILA DE ATENDIMENTO (top 10 por atenção e valor mensal):',
                ...$ativas->take(10)->map(fn ($c, $i) => ($i + 1).". {$c->nome} ({$c->codigo}) — {$c->rotulo()}, atenção {$c->score}/100, ".Customer::brl($c->valor).'/mês, principal motivo: '.($c->sinais[0]['label'] ?? 'sem sinal forte'))->all(),
            ]);
        }

        $linhas = [
            'CONTEXTO: visão geral da carteira (sem cliente específico em foco).',
            "Clientes ativos: {$ativas->count()} | Receita mensal ativa: ".Customer::brl($ativas->sum('valor')).' | Exposição mensal total: '.Customer::brl($ativas->sum('exposicao')),
            'FILA DE ATENDIMENTO (top 10: primeiro quem já está em alerta e, em cada grupo, atenção × valor do contrato):',
            ...$ativas->take(10)->map(fn ($c, $i) => ($i + 1).". {$c->nome} ({$c->codigo}) — {$c->nivel}, atenção {$c->score}/100, ".Customer::brl($c->valor).'/mês, principal motivo: '.($c->sinais[0]['label'] ?? 'sem sinal forte'))->all(),
        ];

        // destaques já calculados: o modelo local erra comparação de números
        $top = $ativas->take(10);
        $linhas[] = 'Entre os 10 da fila: maior atenção = '.self::destaque($top->sortByDesc('score')->first(), fn ($c) => "atenção {$c->score}/100").'; maior contrato = '.self::destaque($top->sortByDesc('valor')->first(), fn ($c) => Customer::brl($c->valor).'/mês').'; menor contrato = '.self::destaque($top->sortBy('valor')->first(), fn ($c) => Customer::brl($c->valor).'/mês').'.';

        return implode("\n", $linhas);
    }

    private static function destaque(?Customer $c, callable $valor): string
    {
        return $c ? "{$c->nome} ({$c->codigo}, ".$valor($c).')' : 'n/d';
    }
}
