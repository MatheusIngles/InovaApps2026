<?php

namespace App\Support\Validacao;

use App\Models\Company;
use App\Support\Llm\Llm;
use App\Support\Risco;
use App\Support\Tenancy\CompanyConfig;
use Illuminate\Support\Facades\Cache;

/** Monta o contexto de evidências da carteira e pede à IA a explicação da configuração recomendada (com texto local se a IA falhar). */
class Configurador
{
    /**
     * A configuração recomendada, em números (o que "Aplicar" faria), sem gravar nada.
     *
     * @return array{ordem: list<array{k: string, rotulo: string, peso_sugerido: float}>, desligadas: list<string>, limiares: array{medio: int, alto: int, critico: int}}
     */
    public static function recomendada(Company $company): array
    {
        return Cache::remember(Backtest::chave($company, 'recomendada'), now()->addHour(), fn () => self::calcularRecomendada($company));
    }

    private static function calcularRecomendada(Company $company): array
    {
        $r = Backtest::resumo($company);
        $ordem = collect($r['variaveis'])->map(fn ($v, $k) => ['k' => $k, 'rotulo' => $v['rotulo'], 'peso_sugerido' => $v['peso_sugerido']])->sortByDesc('peso_sugerido')->values();
        $ativas = $ordem->filter(fn ($v) => $v['peso_sugerido'] > 0)->values();
        $novos = collect(CompanyConfig::pesosPorPosicao($ordem->map(fn ($v) => ['k' => $v['k'], 'ativa' => $v['peso_sugerido'] > 0])->all()))->mapWithKeys(fn ($m) => [$m['k'] => $m['peso']])->all();

        return [
            'ordem' => $ativas->all(),
            'desligadas' => $ordem->filter(fn ($v) => $v['peso_sugerido'] <= 0)->pluck('rotulo')->all(),
            'limiares' => (new Backtest($novos))->limiaresSugeridos(),
        ];
    }

    public static function contexto(Company $company): string
    {
        $r = Backtest::resumo($company);
        $rec = self::recomendada($company);
        $l = $company->limiares();
        $linhas = ['EVIDÊNCIA DA CARTEIRA'];
        $c = $r['limiares']['alto'];
        $linhas[] = "Clientes que cancelaram: {$c['cancelados']}; ativos: {$c['ativos']}.";
        $linhas[] = 'Configuração atual: cortes médio '.$l['medio'].', alto '.$l['alto'].', crítico '.$l['critico'].'; K da fila '.$company->prioridadeK().'.';
        $linhas[] = 'Ordem atual das métricas (peso): '.collect($company->pesos())->map(fn ($p, $k) => Risco::ROTULOS[$k].' '.round($p))->implode('; ').'.';
        $linhas[] = '';
        $linhas[] = 'VARIÁVEIS (separação 0,5 = nada, 1,0 = perfeita; antecedência mediana em meses; alerta em quem ficou = % de retidos com a variável ≥ 50%):';

        foreach (collect($r['variaveis'])->sortByDesc('auc') as $v) {
            $linhas[] = sprintf('- %s: separação %.2f; antecedência %s; alerta em quem ficou %s%%; peso sugerido %s', $v['rotulo'], $v['auc'], $v['antecedencia'] === null ? 'n/d' : $v['antecedencia'].' meses', $v['alarme_falso_pct'], $v['peso_sugerido']);
        }
        $linhas[] = '';
        $linhas[] = 'VARIÁVEIS FORA DO SCORE: são colunas guardadas da planilha que o cálculo da atenção não usa (ele só soma os sinais acima). A separação é o AUC, calculado com a média dos 3 últimos meses de cada cliente, comparando cancelados (mês antes da saída) e retidos (mês mais recente); mostra correlação, não causa.';
        $linhas[] = 'VARIÁVEIS FORA DO SCORE (separação): '.collect($r['extras'])->map(fn ($v) => $v['rotulo'].' '.number_format($v['auc'], 2, ',', ''))->implode('; ').'.';
        $linhas[] = '';
        $linhas[] = 'EFEITO DE CADA CORTE ATUAL (cancelados alertados / antecedência mediana / alerta em quem ficou entre retidos):';
        foreach ($r['limiares'] as $chave => $x) {
            $linhas[] = sprintf('- %s (≥ %d): %d de %d; %s; %s%%', $chave, $x['limiar'], $x['detectados'], $x['cancelados'], $x['mediana_antecedencia'] === null ? 'n/d' : $x['mediana_antecedencia'].' meses', $x['alarme_falso_pct']);
        }
        $linhas[] = '';
        $linhas[] = 'CONFIGURAÇÃO RECOMENDADA PELOS DADOS:';
        $linhas[] = 'Ordem das métricas: '.collect($rec['ordem'])->pluck('rotulo')->implode(' > ').'.';
        $linhas[] = 'Desligar (não separam): '.($rec['desligadas'] ? implode(', ', $rec['desligadas']) : 'nenhuma').'.';
        $linhas[] = "Cortes recomendados: médio {$rec['limiares']['medio']} (alerta em quem ficou até 30%), alto {$rec['limiares']['alto']} (até 8%), crítico {$rec['limiares']['critico']} (até 2%).";

        return implode("\n", $linhas);
    }

    /** @return array{texto: string, fonte: string} */
    public static function sugerir(Company $company): array
    {
        $contexto = self::contexto($company);

        try {
            $r = Llm::responder(str_replace('{{contexto}}', $contexto, config('llm.prompt_configuracao')), [['role' => 'user', 'content' => 'Explique a configuração recomendada para a minha carteira.']], $company->chat()['ollama_model'] ?? null);

            return ['texto' => $r['texto'], 'fonte' => $r['provedor'] === 'api' ? 'Modelo avançado (API)' : 'Modelo local (Ollama)'];
        } catch (\Throwable) {
            return ['texto' => self::textoLocal($company), 'fonte' => 'Resumo por regras (IA indisponível)'];
        }
    }

    /** Explicação escrita por regras, com os mesmos números, para quando nenhuma IA responde. */
    public static function textoLocal(Company $company): string
    {
        $r = Backtest::resumo($company);
        $rec = self::recomendada($company);
        $alto = $r['limiares']['alto'];
        $top = collect($r['variaveis'])->sortByDesc('auc')->take(3)
            ->map(fn ($v) => sprintf('%s (separação %s)', $v['rotulo'], number_format($v['auc'], 2, ',', '')))->implode(', ');
        $desligadas = $rec['desligadas'] ? 'Ficam desligadas por não separarem cancelados de retidos: '.implode(', ', $rec['desligadas']).'.' : 'Nenhuma métrica precisa ser desligada: todas separam algo.';
        $antecedencia = $alto['mediana_antecedencia'] === null ? 'sem antecedência medida' : $alto['mediana_antecedencia'].' meses de antecedência mediana';

        return "**Prioridade das métricas.** As que mais separam quem cancelou de quem ficou: {$top}. {$desligadas}\n\n"
            ."**Cortes de alerta.** Recomendados: Médio ≥ {$rec['limiares']['medio']}, Alto ≥ {$rec['limiares']['alto']}, Crítico ≥ {$rec['limiares']['critico']}. "
            ."Com o corte Alto atual (≥ {$alto['limiar']}), {$alto['detectados']} dos {$alto['cancelados']} cancelados foram alertados antes de sair, com {$antecedencia} e {$alto['alarme_falso_pct']}% de alerta em quem ficou entre os que ficaram. Cortes mais baixos avisam mais cedo, mas geram mais alerta em quem ficou.\n\n"
            .'**Equilíbrio atenção × valor.** O K da fila define o quanto o valor do contrato pesa contra a atenção (padrão '.Company::PRIORIDADE_PADRAO.'); é uma decisão de negócio, não dos dados.'."\n\n"
            .'**Cuidados.** São poucos cancelamentos e a análise usa os mesmos dados da calibração; a atenção ordena o atendimento, não é probabilidade de cancelamento. Tudo pode ser ajustado em Configurações.';
    }
}
