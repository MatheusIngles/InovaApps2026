<?php

namespace App\Support\Validacao;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\MetricValue;
use App\Support\Metricas\MetricRisk;
use App\Support\Risco;
use App\Support\Tenancy\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Backtest do risco com o histórico da carteira: responde às três perguntas do desafio
 * (antecedência do sinal, separação/alarme falso e quanto cada variável pesa) só com os dados importados.
 *
 * Para cada cliente e mês (com pelo menos 3 meses de histórico) recalcula severidades e risco como o sistema faria
 * naquele mês. "Cancelados": os meses antes da saída. "Retidos": clientes ativos, em todos os meses.
 * Limite honesto: poucos eventos (a base tem 22 cancelamentos) e avaliação nos mesmos dados usados para calibrar.
 */
class Backtest
{
    /** Horizonte (meses) em que um alerta conta como "acertou" se o cliente cancelou depois dele. */
    public const HORIZONTE = 6;

    public const MINIMO_CANCELADOS = 5;

    /** Variáveis candidatas que o risco não usa (média dos 3 últimos meses, valor bruto). */
    public const EXTRAS = [
        'tickets_critical' => 'Chamados críticos',
        'avg_resolution_hours' => 'Tempo médio de resolução (h)',
        'tickets_opened' => 'Volume de chamados abertos',
    ];

    /** @var array<int, array{cancelado: bool, saida: ?string, valor: float, cliente: Customer, meses: list<array{mes: string, sev: array<string, float>, score: int, extra: array<string, float>}>}> */
    private array $series = [];

    /** @var array<string, float|int> */
    private array $pesos;

    /** @var array<string, float> */
    private array $customWeights = [];

    /** @var array<string, string> */
    private array $customLabels = [];

    /** @param array<string, float|int> $pesos */
    public function __construct(array $pesos)
    {
        $this->pesos = $pesos;
        $this->montar();
    }

    private function montar(): void
    {
        $clientes = Customer::with(['metrics' => fn ($q) => $q->orderBy('reference_month'), 'npsResponses' => fn ($q) => $q->orderBy('reference_month'), 'metricValues' => fn ($q) => $q->orderBy('reference_month'), 'periods' => fn ($q) => $q->orderBy('reference_month')])->get();
        $definitions = app(CompanyContext::class)->current()?->metricDefinitions()->get();
        $this->customWeights = $definitions?->filter(fn ($definition) => $definition->enabled && $definition->value_type !== 'text')
            ->mapWithKeys(fn ($definition): array => ['custom:'.$definition->code => (float) $definition->weight])->all() ?? [];
        $this->customLabels = $definitions?->filter(fn ($definition) => $definition->enabled && $definition->value_type !== 'text')
            ->mapWithKeys(fn ($definition): array => ['custom:'.$definition->code => $definition->label])->all() ?? [];

        foreach ($clientes as $c) {
            $cancelado = $c->status === 'Cancelado';
            $meses = self::mesesDoCliente($c, $this->pesos, $definitions);

            if ($meses) {
                $this->series[$c->id] = ['cancelado' => $cancelado, 'saida' => $c->cancelled_at?->format('Y-m'), 'valor' => (float) $c->monthly_value, 'cliente' => $c, 'meses' => $meses];
            }
        }
    }

    /**
     * Severidades, risco e variáveis extras de cada mês de um cliente (a partir do 3º mês, que fecha a janela do risco).
     * Para cancelados só entram os meses anteriores à saída.
     *
     * @param  array<string, float|int>  $pesos
     * @return list<array{mes: string, sev: array<string, float>, score: int, extra: array<string, float>}>
     */
    public static function mesesDoCliente(Customer $c, array $pesos, ?Collection $definitions = null): array
    {
        $c->loadMissing(['metrics' => fn ($q) => $q->orderBy('reference_month'), 'npsResponses' => fn ($q) => $q->orderBy('reference_month'), 'metricValues' => fn ($q) => $q->orderBy('reference_month'), 'periods' => fn ($q) => $q->orderBy('reference_month')]);
        $definitions ??= $c->company->metricDefinitions()->get();
        $enabledDefinitionIds = $definitions->filter(fn ($definition) => $definition->enabled && $definition->value_type !== 'text' && (float) $definition->weight > 0)->pluck('id');
        $cancelado = $c->status === 'Cancelado';
        $metricas = $c->metrics->filter(fn ($m) => ! $cancelado || $c->cancelled_at === null || $m->reference_month->lt($c->cancelled_at))->values();
        $values = $c->metricValues->filter(fn ($value) => $enabledDefinitionIds->contains($value->metric_definition_id) && $value->value !== null
            && (! $cancelado || $c->cancelled_at === null || $value->reference_month->lt($c->cancelled_at)))->values();
        $periods = $c->periods->filter(fn ($period) => ! $cancelado || $c->cancelled_at === null || $period->reference_month->lt($c->cancelled_at));
        $referenceMonths = $metricas->pluck('reference_month')->merge($values->pluck('reference_month'))->merge($periods->pluck('reference_month'))
            ->map(fn ($date) => $date->format('Y-m'))->unique()->sort()->values();
        $meses = [];

        foreach ($referenceMonths as $month) {
            $reference = Carbon::parse($month.'-01');
            $availableMetrics = $metricas->filter(fn ($metric) => $metric->reference_month->lte($reference));
            $windowStart = $reference->copy()->subMonths(2);
            $hasCustomObservation = $values->contains(fn ($value) => $value->reference_month->gte($windowStart) && $value->reference_month->lte($reference));
            if ($availableMetrics->count() < 3 && ! $hasCustomObservation) {
                continue; // sinais padrão precisam de 3 meses; métricas próprias podem começar antes
            }
            $janela = $availableMetrics->map(fn ($x): array => [
                'mes' => $x->reference_month->format('Y-m'),
                'chamados_abertos' => $x->tickets_opened, 'chamados_reabertos' => $x->tickets_reopened, 'pct_sla_cumprido' => $x->sla_percentage,
                'reclamacoes_formais' => $x->formal_complaints, 'uso_plataforma_pct' => $x->platform_usage_percentage,
                'dias_atraso_pagamento' => $x->payment_delay_days, 'reunioes_previstas' => $x->meetings_expected, 'reunioes_realizadas' => $x->meetings_completed,
            ])->values()->all();
            $nps = $c->npsResponses->filter(fn ($r) => $r->reference_month->lte($reference))
                ->map(fn ($r): array => ['mes' => $r->reference_month->format('Y-m'), 'respondeu' => $r->answered, 'nota_nps' => $r->score])->values()->all();
            $r = MetricRisk::calcular($janela, $nps, $pesos, Risco::LIMIARES, $definitions, $values, $reference);
            $ult3 = $availableMetrics->slice(-3);

            $meses[] = [
                'mes' => $month,
                'sev' => array_combine(array_keys(Risco::PESOS), $r['sev']) + $r['custom_severity'],
                'has_base' => $availableMetrics->isNotEmpty(),
                'score' => $r['score'],
                'extra' => array_map(fn (string $col): float => (float) $ult3->avg($col), array_combine(array_keys(self::EXTRAS), array_keys(self::EXTRAS))),
            ];
        }

        return $meses;
    }

    private function saidaEm(string $mes): int
    {
        return (int) substr($mes, 0, 4) * 12 + (int) substr($mes, 5, 2);
    }

    /** Meses entre um mês e a saída (1 = o mês imediatamente anterior à saída). */
    private function antes(array $s, string $mes): int
    {
        return $this->saidaEm($s['saida'] ?? $mes) - $this->saidaEm($mes);
    }

    /**
     * Antecedência do alerta persistente: há quantos meses da saída o risco passou de $limiar e ficou acima até o fim.
     * Nulo = não estava em alerta no último mês antes da saída (não detectado).
     *
     * @param  callable(array): float  $valor  valor do mês a comparar
     */
    private function antecedencia(array $s, callable $valor, float $limiar): ?int
    {
        $lead = null;

        foreach (array_reverse($s['meses']) as $m) {
            if ($valor($m) < $limiar) {
                break;
            }
            $lead = $this->antes($s, $m['mes']);
        }

        return $lead;
    }

    private function cancelados(): array
    {
        return array_filter($this->series, fn ($s) => $s['cancelado']);
    }

    private function retidos(): array
    {
        return array_filter($this->series, fn ($s) => ! $s['cancelado']);
    }

    /** Chave de cache: muda quando mudam os pesos, a quantidade de clientes ou as métricas da empresa. */
    public static function chave(Company $company, string $parte): string
    {
        return 'backtest:'.$parte.':'.$company->id.':'.md5(json_encode([$company->pesos(), $company->limiares()])).':'.Customer::count().':'.CustomerMetric::whereIn('customer_id', Customer::pluck('id'))->max('updated_at').':'.MetricValue::where('company_id', $company->id)->max('updated_at').':'.$company->metricDefinitions()->max('updated_at');
    }

    public static function invalidar(Company $company): void
    {
        app(CompanyContext::class)->within($company, fn () => Cache::forget(self::chave($company, 'resumo:v2')));
    }

    /**
     * Tudo que a tela de evidências mostra, calculado uma vez e guardado em cache até mudarem dados ou pesos da empresa.
     *
     * @return array<string, mixed>
     */
    public static function resumo(Company $company): array
    {
        $pesos = $company->pesos();

        return Cache::remember(self::chave($company, 'resumo:v2'), now()->addHour(), function () use ($company, $pesos): array {
            $b = new self($pesos);
            $l = $company->limiares();
            $niveis = ['medio' => $l['medio'], 'alto' => $l['alto'], 'critico' => $l['critico']];

            return [
                'limiares' => array_map(fn (int $x) => $b->porLimiar($x), $niveis),
                'cancelamentos' => array_map(fn (int $x) => $b->porCancelamento($x), $niveis),
                'variaveis' => $b->porVariavel(),
                'metricas_proprias' => $b->porMetricaPropria(),
                'extras' => $b->extras(),
                'perfis' => $b->perfis(),
                'segmentos' => $b->porSegmento(),
                'validacao_temporal' => $b->validacaoTemporal(),
                'evidencia_suficiente' => $b->evidenciaSuficiente(),
                'auc_atual' => $b->aucScore(),
                'auc_sugerido' => $b->aucComPesosSugeridos(),
            ];
        });
    }

    /** Resultado por limiar de alerta: detecção, antecedência, precisão e carga de alarmes. */
    public function porLimiar(int $limiar): array
    {
        $canc = $this->cancelados();
        $leads = array_map(fn ($s) => $this->antecedencia($s, fn ($m) => $m['score'], $limiar), $canc);
        $detectados = array_filter($leads, fn ($l) => $l !== null);
        $tres = array_filter($detectados, fn ($l) => $l >= 3);

        $mesesRet = 0;
        $alertasRet = 0;
        $porMes = [];
        foreach ($this->retidos() as $s) {
            foreach ($s['meses'] as $m) {
                $mesesRet++;
                if ($m['score'] >= $limiar) {
                    $alertasRet++;
                    $porMes[$m['mes']] = ($porMes[$m['mes']] ?? 0) + 1;
                }
            }
        }

        // precisão: dos alertas de um mês (de qualquer cliente), quantos cancelaram nos próximos HORIZONTE meses.
        // Só entram meses cujo horizonte inteiro já foi observado.
        $ultimoMes = $this->series ? max(array_map(fn ($s) => $this->saidaEm(end($s['meses'])['mes']), $this->series)) : 0; // sem histórico, nada a medir
        $alertas = 0;
        $acertos = 0;
        foreach ($this->series as $s) {
            foreach ($s['meses'] as $m) {
                $t = $this->saidaEm($m['mes']);
                if ($m['score'] < $limiar || $t + self::HORIZONTE > $ultimoMes) {
                    continue;
                }
                $alertas++;
                if ($s['cancelado'] && $this->antes($s, $m['mes']) <= self::HORIZONTE) {
                    $acertos++;
                }
            }
        }

        $ativosAgora = array_filter($this->retidos(), fn ($s) => end($s['meses'])['score'] >= $limiar);

        return [
            'limiar' => $limiar,
            'cancelados' => count($canc),
            'detectados' => count($detectados),
            'com_3_meses' => count($tres),
            'mediana_antecedencia' => $detectados ? $this->mediana(array_values($detectados)) : null,
            'alarme_falso_pct' => $mesesRet ? round(100 * $alertasRet / $mesesRet, 1) : 0,
            'alertas_por_mes' => count($porMes) ? round(array_sum($porMes) / count($porMes), 1) : 0,
            'ativos_em_alerta_agora' => count($ativosAgora),
            'ativos' => count($this->retidos()),
            'precisao_pct' => $alertas ? round(100 * $acertos / $alertas, 1) : null,
        ];
    }

    /** Linha por cancelamento: quando o alerta persistente começou e com quanta antecedência. */
    public function porCancelamento(int $limiar): array
    {
        $linhas = [];

        foreach ($this->cancelados() as $s) {
            $lead = $this->antecedencia($s, fn ($m) => $m['score'], $limiar);
            $ultimo = end($s['meses']);
            $linhas[] = [
                'codigo' => $s['cliente']->external_code, 'nome' => $s['cliente']->displayName(), 'saida' => $s['saida'], 'valor' => $s['valor'],
                'antecedencia' => $lead, 'score_final' => $ultimo['score'],
                'alerta_desde' => $lead !== null ? date('Y-m', strtotime($s['saida'].'-01 -'.$lead.' month')) : null,
            ];
        }
        usort($linhas, fn ($a, $b) => ($b['antecedencia'] ?? -1) <=> ($a['antecedencia'] ?? -1));

        return $linhas;
    }

    /**
     * Por variável do score: quanto separa cancelados de retidos (AUC: 0,5 = nada, 1 = perfeito), antecedência mediana
     * e alarme falso (severidade ≥ 0,5 em retidos). Cancelados: último mês antes da saída. Retidos: mês mais recente.
     */
    public function porVariavel(): array
    {
        $canc = array_map(fn ($s) => end($s['meses']), $this->cancelados());
        $ret = array_map(fn ($s) => end($s['meses']), $this->retidos());
        $out = [];

        foreach (array_keys(Risco::PESOS) as $k) {
            $vc = array_values(array_map(fn ($m) => $m['sev'][$k], $canc));
            $vr = array_values(array_map(fn ($m) => $m['sev'][$k], $ret));
            $leads = array_filter(array_map(fn ($s) => $this->antecedencia($s, fn ($m) => $m['sev'][$k] * 100, 50), $this->cancelados()), fn ($l) => $l !== null);

            $out[$k] = [
                'rotulo' => Risco::ROTULOS[$k],
                'auc' => $this->auc($vc, $vr),
                'media_cancelados' => round(array_sum($vc) / max(1, count($vc)), 2),
                'media_retidos' => round(array_sum($vr) / max(1, count($vr)), 2),
                'antecedencia' => $leads ? $this->mediana(array_values($leads)) : null,
                'detectados' => count($leads),
                'alarme_falso_pct' => round(100 * count(array_filter($vr, fn ($v) => $v >= 0.5)) / max(1, count($vr)), 1),
                'peso_atual' => $this->pesos[$k] ?? 0,
            ];
        }

        // pesos sugeridos: proporcionais ao quanto cada variável separa (AUC acima de 0,5); sem separação = peso 0
        $ganho = array_map(fn ($v) => max(0.0, $v['auc'] - 0.5), $out);
        $soma = array_sum($ganho);
        foreach ($out as $k => &$v) {
            $v['peso_sugerido'] = $soma > 0 ? round($ganho[$k] / $soma * 100, 1) : 0;
        }

        return $out;
    }

    /** Métricas próprias são avaliadas só onde há observação na janela, sem imputar zero para ausência de dados. */
    public function porMetricaPropria(): array
    {
        $out = [];

        foreach ($this->customLabels as $key => $label) {
            $canc = array_values(array_filter(array_map(fn ($s) => end($s['meses'])['sev'][$key] ?? null, $this->cancelados()), fn ($v) => $v !== null));
            $ret = array_values(array_filter(array_map(fn ($s) => end($s['meses'])['sev'][$key] ?? null, $this->retidos()), fn ($v) => $v !== null));
            $observados = array_filter($this->cancelados(), fn ($s) => isset(end($s['meses'])['sev'][$key]));
            $leads = array_filter(array_map(fn ($s) => $this->antecedencia($s, fn ($m) => ($m['sev'][$key] ?? 0) * 100, 50), $observados), fn ($v) => $v !== null);

            $out[$key] = [
                'rotulo' => $label,
                'auc' => $canc && $ret ? $this->auc($canc, $ret) : null,
                'cancelados' => count($canc),
                'retidos' => count($ret),
                'media_cancelados' => $canc ? round(array_sum($canc) / count($canc), 2) : null,
                'media_retidos' => $ret ? round(array_sum($ret) / count($ret), 2) : null,
                'antecedencia' => $leads ? $this->mediana(array_values($leads)) : null,
                'detectados' => count($leads),
                'alarme_falso_pct' => $ret ? round(100 * count(array_filter($ret, fn ($v) => $v >= 0.5)) / count($ret), 1) : null,
                'peso_atual' => $this->customWeights[$key],
            ];
        }

        return $out;
    }

    /**
     * Validação em período separado (sem olhar o futuro): calibra pesos e corte só com o que se sabia até o fim do ano
     * anterior ao último cancelamento e testa nos cancelamentos seguintes.
     *
     * Treino: cancelados até o corte (último mês antes da saída) contra os clientes ainda ativos naquele mês (mesmo os que
     * saíram depois: na época eram ativos). Teste: cancelados depois do corte contra os que nunca cancelaram (mês mais recente).
     *
     * @return array{suficiente: bool, corte: ?string, treino: array{cancelados: int, ativos: int}, teste: array{cancelados: int, ativos: int}, pesos_treino: array<string, float>, corte_alto: ?int, auc: array<string, float>, detectados: int, alarme_falso_pct: float}
     */
    public function validacaoTemporal(): array
    {
        $saidas = array_filter(array_column($this->cancelados(), 'saida'));
        $vazio = ['suficiente' => false, 'corte' => null, 'treino' => ['cancelados' => 0, 'ativos' => 0], 'teste' => ['cancelados' => 0, 'ativos' => 0], 'pesos_treino' => [], 'corte_alto' => null, 'auc' => [], 'detectados' => 0, 'alarme_falso_pct' => 0.0];

        if (! $saidas) {
            return $vazio;
        }
        $corte = ((int) substr(max($saidas), 0, 4) - 1).'-12';
        $daSaida = fn (array $s): array => end($s['meses']);
        $noCorte = fn (array $s): ?array => collect($s['meses'])->firstWhere('mes', $corte);

        $trainPos = array_values(array_map($daSaida, array_filter($this->cancelados(), fn ($s) => $s['saida'] <= $corte)));
        $trainNeg = array_values(array_filter(array_map(
            fn ($s) => ($s['cancelado'] && $s['saida'] <= $corte) ? null : $noCorte($s),
            $this->series,
        )));
        $testPos = array_values(array_map($daSaida, array_filter($this->cancelados(), fn ($s) => $s['saida'] > $corte)));
        $testNeg = array_values(array_map($daSaida, $this->retidos()));

        $r = $vazio;
        $r['corte'] = $corte;
        $r['treino'] = ['cancelados' => count($trainPos), 'ativos' => count($trainNeg)];
        $r['teste'] = ['cancelados' => count($testPos), 'ativos' => count($testNeg)];

        if (count($trainPos) < self::MINIMO_CANCELADOS || count($testPos) < 3 || count($trainNeg) < self::MINIMO_CANCELADOS || count($testNeg) < self::MINIMO_CANCELADOS) {
            return $r;
        }

        // pesos calibrados só no treino: proporcionais ao quanto cada variável separa (acima de 0,5)
        $ganho = [];
        foreach (array_keys(Risco::PESOS) as $k) {
            $ganho[$k] = max(0.0, $this->auc(array_column(array_column($trainPos, 'sev'), $k), array_column(array_column($trainNeg, 'sev'), $k)) - 0.5);
        }
        $soma = array_sum($ganho);
        $pesos = $soma > 0 ? array_map(fn ($g) => round($g / $soma * 100, 1), $ganho) : Risco::PESOS;
        $f = fn (array $pesosUsados) => fn (array $m): float => $this->scoreComPesos($m, $pesosUsados);
        $auc = fn (array $pos, array $neg, array $p) => $this->auc(array_map($f($p), $pos), array_map($f($p), $neg));

        // corte "Alto" do treino: o menor em que o alarme falso entre os ativos do treino fica em até 8%
        $scoresNeg = array_map($f($pesos), $trainNeg);
        $corteAlto = null;
        foreach (range(1, 100) as $t) {
            if (count(array_filter($scoresNeg, fn ($v) => $v >= $t)) / count($scoresNeg) <= 0.08) {
                $corteAlto = $t;
                break;
            }
        }
        $testPosScores = array_map($f($pesos), $testPos);
        $testNegScores = array_map($f($pesos), $testNeg);

        $r['suficiente'] = true;
        $r['pesos_treino'] = $pesos;
        $r['corte_alto'] = $corteAlto;
        $r['auc'] = [
            'treino' => $auc($trainPos, $trainNeg, $pesos),
            'teste_pesos_treino' => $auc($testPos, $testNeg, $pesos),
            'teste_pesos_padrao' => $auc($testPos, $testNeg, Risco::PESOS),
        ];
        $r['detectados'] = $corteAlto === null ? 0 : count(array_filter($testPosScores, fn ($v) => $v >= $corteAlto));
        $r['alarme_falso_pct'] = $corteAlto === null ? 0.0 : round(100 * count(array_filter($testNegScores, fn ($v) => $v >= $corteAlto)) / count($testNegScores), 1);

        return $r;
    }

    /** Variáveis candidatas que o risco não usa, na mesma régua (AUC). */
    public function extras(): array
    {
        $canc = array_map(fn ($s) => end($s['meses']), $this->cancelados());
        $ret = array_map(fn ($s) => end($s['meses']), $this->retidos());
        $out = [];

        foreach (self::EXTRAS as $col => $rotulo) {
            $vc = array_values(array_map(fn ($m) => $m['extra'][$col], $canc));
            $vr = array_values(array_map(fn ($m) => $m['extra'][$col], $ret));
            $out[$col] = ['rotulo' => $rotulo, 'auc' => $this->auc($vc, $vr), 'media_cancelados' => round(array_sum($vc) / max(1, count($vc)), 1), 'media_retidos' => round(array_sum($vr) / max(1, count($vr)), 1)];
        }

        return $out;
    }

    /**
     * Por segmento: o que estava elevado nos cancelados (último mês antes da saída) em comparação com os retidos
     * do mesmo segmento, e quais clientes ativos hoje mostram o mesmo padrão.
     */
    public function porSegmento(): array
    {
        $porSeg = [];
        foreach ($this->series as $s) {
            $porSeg[$s['cliente']->segment][] = $s;
        }
        ksort($porSeg);
        $out = [];

        foreach ($porSeg as $segmento => $grupo) {
            $canc = array_values(array_filter($grupo, fn ($s) => $s['cancelado']));
            $ret = array_values(array_filter($grupo, fn ($s) => ! $s['cancelado']));
            $media = fn (array $lista, callable $f) => $lista ? array_sum(array_map($f, $lista)) / count($lista) : 0;
            $itens = [];

            foreach (Risco::ROTULOS as $k => $rotulo) {
                $mc = $media($canc, fn ($s) => end($s['meses'])['sev'][$k]);
                $mr = $media($ret, fn ($s) => end($s['meses'])['sev'][$k]);
                $itens[] = ['k' => $k, 'rotulo' => $rotulo, 'extra' => false, 'cancelados' => $mc, 'retidos' => $mr, 'efeito' => $mc - $mr,
                    'texto' => sprintf('gravidade %d%% nos cancelados contra %d%% nos que ficaram', round($mc * 100), round($mr * 100))];
            }
            foreach (self::EXTRAS as $col => $rotulo) {
                $mc = $media($canc, fn ($s) => end($s['meses'])['extra'][$col]);
                $mr = $media($ret, fn ($s) => end($s['meses'])['extra'][$col]);
                $itens[] = ['k' => $col, 'rotulo' => $rotulo, 'extra' => true, 'cancelados' => $mc, 'retidos' => $mr,
                    'efeito' => min(1.0, ($mc - $mr) / max(1.0, $mr)), 'texto' => sprintf('média de %s nos cancelados contra %s nos que ficaram', number_format($mc, 1, ',', '.'), number_format($mr, 1, ',', '.'))];
            }
            usort($itens, fn ($a, $b) => $b['efeito'] <=> $a['efeito']);
            // variáveis do score: as 3 mais elevadas (a partir de 15 pontos acima dos retidos); extras (fora do risco): só se 50% acima
            $elevados = array_slice(array_filter($itens, fn ($i) => ! $i['extra'] && $i['efeito'] >= 0.15), 0, 3);
            $elevados = array_merge($elevados, array_slice(array_filter($itens, fn ($i) => $i['extra'] && $i['efeito'] >= 0.5), 0, 2));

            // ativos que hoje repetem o padrão: variável elevada acima do que os retidos mostram (severidade ≥ 50% ou 30% acima da média dos retidos)
            $expostos = [];
            foreach ($ret as $s) {
                $ult = end($s['meses']);
                $hits = [];
                foreach ($elevados as $i) {
                    $v = $i['extra'] ? $ult['extra'][$i['k']] : $ult['sev'][$i['k']];
                    if ($i['extra'] ? $v >= $i['retidos'] * 1.3 : $v >= 0.5) {
                        $hits[] = $i['rotulo'];
                    }
                }
                if ($hits) {
                    $expostos[] = ['codigo' => $s['cliente']->external_code, 'nome' => $s['cliente']->displayName(), 'valor' => $s['valor'], 'score' => $ult['score'], 'variaveis' => $hits];
                }
            }
            usort($expostos, fn ($a, $b) => [count($b['variaveis']), $b['valor']] <=> [count($a['variaveis']), $a['valor']]);

            $out[$segmento] = [
                'segmento' => $segmento, 'total' => count($grupo), 'cancelados' => count($canc), 'retidos' => count($ret),
                'pct_cancelou' => round(100 * count($canc) / max(1, count($grupo)), 1),
                'receita_perdida' => array_sum(array_map(fn ($s) => $s['valor'], $canc)),
                'elevados' => array_values($elevados), 'expostos' => $expostos,
                'receita_exposta' => array_sum(array_map(fn ($e) => $e['valor'], $expostos)),
            ];
        }
        uasort($out, fn ($a, $b) => $b['pct_cancelou'] <=> $a['pct_cancelou']);

        return $out;
    }

    /** Poucos cancelamentos não sustentam calibrar pesos e cortes: abaixo disso a configuração padrão é mantida. */
    public function evidenciaSuficiente(): bool
    {
        return count($this->cancelados()) >= self::MINIMO_CANCELADOS && count($this->retidos()) >= self::MINIMO_CANCELADOS;
    }

    /**
     * Cortes de nível calibrados pela carteira: o menor risco em que o alarme falso (meses de clientes retidos acima do corte)
     * fica dentro do alvo: 30% para Médio, 8% para Alto e 2% para Crítico. Respeita a ordem e um espaço mínimo entre níveis.
     *
     * @return array{medio: int, alto: int, critico: int}
     */
    public function limiaresSugeridos(): array
    {
        $scores = [];
        foreach ($this->retidos() as $s) {
            foreach ($s['meses'] as $m) {
                $scores[] = $m['score'];
            }
        }
        $corte = function (float $alvo) use ($scores): int {
            for ($c = 1; $c <= 100; $c++) {
                if (count(array_filter($scores, fn ($x) => $x >= $c)) / max(1, count($scores)) <= $alvo) {
                    return $c;
                }
            }

            return 100;
        };
        $medio = max(1, min(90, $corte(0.30)));
        $alto = max($medio + 5, min(95, $corte(0.08)));
        $critico = max($alto + 5, min(100, $corte(0.02)));

        return ['medio' => $medio, 'alto' => $alto, 'critico' => $critico];
    }

    /** Taxa de cancelamento por segmento, porte e plano. */
    public function perfis(): array
    {
        $out = [];

        foreach (['segment' => 'Segmento', 'size' => 'Porte', 'plan' => 'Plano'] as $campo => $rotulo) {
            $grupos = [];
            foreach ($this->series as $s) {
                $g = $s['cliente']->{$campo};
                $grupos[$g]['total'] = ($grupos[$g]['total'] ?? 0) + 1;
                $grupos[$g]['cancelados'] = ($grupos[$g]['cancelados'] ?? 0) + ($s['cancelado'] ? 1 : 0);
            }
            ksort($grupos);
            $out[$rotulo] = array_map(fn ($g, $nome) => ['nome' => $nome, 'total' => $g['total'], 'cancelados' => $g['cancelados'], 'pct' => round(100 * $g['cancelados'] / $g['total'], 1)], $grupos, array_keys($grupos));
        }

        return $out;
    }

    /** AUC do risco completo (último mês antes da saída vs. mês mais recente dos retidos) com os pesos dados. */
    public function aucScore(?array $pesos = null): float
    {
        $pesos ??= $this->pesos;
        $f = fn (array $s) => $this->scoreComPesos(end($s['meses']), $pesos);

        return $this->auc(array_values(array_map($f, $this->cancelados())), array_values(array_map($f, $this->retidos())));
    }

    private function scoreComPesos(array $month, array $baseWeights): float
    {
        $weights = ($month['has_base'] ? $baseWeights : array_fill_keys(array_keys(Risco::PESOS), 0))
            + array_intersect_key($this->customWeights, $month['sev']);

        return array_sum(Risco::pontos($month['sev'], $weights));
    }

    /** @param array<string, float> $sugeridos */
    public function aucComPesosSugeridos(): float
    {
        return $this->aucScore(array_map(fn ($v) => $v['peso_sugerido'], $this->porVariavel()));
    }

    /** AUC (Mann-Whitney): chance de um cancelado ter valor maior que um retido, sorteados ao acaso. */
    private function auc(array $positivos, array $negativos): float
    {
        if (! $positivos || ! $negativos) {
            return 0.5;
        }
        $pontos = 0.0;
        foreach ($positivos as $p) {
            foreach ($negativos as $n) {
                $pontos += $p > $n ? 1 : ($p == $n ? 0.5 : 0);
            }
        }

        return round($pontos / (count($positivos) * count($negativos)), 3);
    }

    private function mediana(array $v): float
    {
        sort($v);
        $m = intdiv(count($v), 2);

        return count($v) % 2 ? $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }
}
