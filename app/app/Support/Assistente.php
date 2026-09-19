<?php

namespace App\Support;

use App\Models\Customer;
use App\Support\Llm\Contexto;
use Illuminate\Support\Str;

/**
 * Chatbot local (regras por palavra-chave, sem API externa).
 */
class Assistente
{
    public static function responder(string $pergunta, ?string $id = null): string
    {
        $q = Str::lower(Str::ascii($pergunta));
        if (! $id && preg_match('/c\d{3}/', $q, $m)) {
            $id = $m[0];
        }
        $c = $id ? Customer::dashboard()->where('customers.external_code', strtoupper($id))->first() : null;

        if (Str::contains($q, ['metrica', 'peso', 'limiar', 'criterio de risco', 'prioridades configuradas', 'minhas prioridades'])) {
            $resposta = Contexto::prioridades();

            if ($c) {
                $resposta .= "\n\n{$c->nome}: risco {$c->score}% ({$c->nivel}).";
                $resposta .= "\nSinais que mais contribuíram nesta avaliação: ".
                    (collect($c->sinais)->map(fn ($s) => "{$s['label']} (+{$s['pts']} pts)")->join(', ') ?: 'nenhum sinal em destaque').'.';
            }

            return $resposta;
        }

        return $c ? self::sobreEmpresa($c, $q) : self::sobreCarteira($q);
    }

    private static function sobreEmpresa(Customer $c, string $q): string
    {
        $cab = "{$c['nome']} ({$c['codigo']}) — {$c['nivel']}, risco {$c['score']}%.";
        $sinais = collect($c['sinais']);
        if ($c['status'] === 'Cancelado') {
            $cab .= " Cancelou em {$c['mes_cancel']}.";
        }

        if (Str::contains($q, ['fazer', 'acao', 'recomend', 'conduta', 'plano'])) {
            return $sinais->isEmpty() ? "$cab\nSem sinais relevantes: manter cadência normal de acompanhamento."
                : "$cab\nO que fazer, em ordem:\n".$sinais->map(fn ($s, $i) => ($i + 1).". {$s['acao']}")->join("\n");
        }
        if (Str::contains($q, ['similar', 'parecid', 'cancelaram', 'fechou', 'fecharam'])) {
            return "$cab\nCancelados com perfil parecido:\n".collect($c['similares'])
                ->map(fn ($s) => "• {$s['nome']} — {$s['sim']}% de semelhança, saiu em {$s['mes_cancel']}")->join("\n");
        }
        if (Str::contains($q, ['nps', 'satisf', 'nota'])) {
            $n = collect($c['nps'])->map(fn ($x) => "{$x['mes']}: ".($x['nota'] ?? 'sem resposta'))->join("\n");

            return "$cab\nHistórico de pesquisas:\n$n";
        }
        if (Str::contains($q, ['valor', 'contrato', 'plano', 'quanto', 'receita'])) {
            return "$cab\nPlano {$c['plan']}, ".Customer::brl($c->valor)."/mês, SLA {$c->sla_h}h, cliente desde {$c->inicio}. Exposição mensal indicativa: ".Customer::brl($c->exposicao).'.';
        }

        // padrão / "por que": evidências
        return $sinais->isEmpty() ? "$cab\nNenhum sinal relevante nos últimos 3 meses."
            : "$cab\nEvidências (últimos 3 meses):\n".$sinais->map(fn ($s) => "• {$s['texto']} (+{$s['pts']} pts)")->join("\n");
    }

    private static function sobreCarteira(string $q): string
    {
        $a = Customer::ativas();
        $risco = $a->where('score', '>=', 40);

        if (Str::contains($q, ['receita', 'exposi', 'dinheiro', 'financeir'])) {
            return 'Exposição mensal indicativa (risco × valor): '.Customer::brl($a->sum('exposicao')).' de '.Customer::brl($a->sum('valor')).
                '. Clientes com risco alto/crítico somam '.Customer::brl($risco->sum('valor')).'/mês.';
        }
        if (Str::contains($q, ['segmento', 'setor'])) {
            return "Risco médio por segmento (ativos):\n".$a->groupBy('segmento')->map(fn ($g) => round($g->avg('score')))
                ->sortDesc()->map(fn ($v, $k) => "• $k: $v")->join("\n");
        }
        if (Str::contains($q, ['cancel', 'churn', 'saiu', 'sairam'])) {
            $x = Customer::dashboard()->where('customers.status', 'Cancelado')->get();

            return "{$x->count()} clientes cancelaram (".Customer::brl($x->sum('valor')).'/mês). Na última avaliação anterior à saída, tinham risco médio '.round($x->avg('score')).
                ' vs '.round($a->avg('score')).' dos ativos na avaliação mais recente; comparação exploratória.';
        }
        if (Str::contains($q, ['resumo', 'quantos', 'carteira', 'geral'])) {
            return "{$a->count()} clientes ativos: ".collect(['Crítico', 'Alto', 'Médio', 'Baixo'])
                ->map(fn ($n) => $a->where('nivel', $n)->count()." $n")->join(', ').'.';
        }
        if (Str::contains($q, ['prioridade', 'quem', 'primeiro', 'ligar', 'falar', 'ordem', 'critico'])) {
            return "Fale primeiro com (maior receita em risco):\n".$a->take(5)->map(fn ($c, $i) => ($i + 1).". {$c['nome']} ({$c['codigo']}) — {$c['nivel']}, ".
                Customer::brl($c->valor).'/mês, motivo: '.($c->sinais[0]['label'] ?? 'sem sinal forte'))->join("\n");
        }

        return 'Posso responder sobre a carteira (resumo, quem ligar primeiro, receita em risco, segmentos, cancelamentos) ou sobre um cliente: cite o código, ex.: "por que C012 está em risco?" ou "o que fazer com C012?".';
    }
}
