<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use App\Models\MetricDefinition;
use App\Support\Risco;
use App\Support\RiskService;
use App\Support\Validacao\Backtest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** CRUD das configurações disponíveis ao usuário: pesos, limiares e tema. */
class CompanyConfig
{
    public const FONTES = ['Plus Jakarta Sans', 'Inter', 'Poppins', 'Roboto', 'Nunito', 'Lora'];

    /** Create: cria uma empresa (tenant) com a configuração padrão. */
    public static function criar(string $nome, ?string $slug = null): Company
    {
        $base = Str::slug($slug ?: $nome) ?: 'empresa';
        $slug = $base;

        for ($i = 2; Company::where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return Company::create(['name' => $nome, 'slug' => $slug]);
    }

    /** Read: configuração efetiva (padrões + personalizações) no formato do formulário. */
    public static function ler(Company $company): array
    {
        return [
            'metricas' => collect($company->pesos())->map(fn ($peso, $k) => ['k' => $k, 'peso' => $peso, 'ativa' => $peso > 0])->values()->all(),
            'limiares' => $company->limiares(),
            'tema' => $company->tema(),
            'prioridade' => $company->prioridadeK(),
            'ia' => $company->chat()['enabled'],
        ];
    }

    /**
     * Lista única de prioridade (a da aba Prioridades): os 8 sinais padrão (se a empresa os tem) e as métricas da
     * empresa que entram na atenção, na ordem salva. Métricas sem posição salva entram pelo peso: antes da primeira
     * que pesa menos que elas (desligadas vão para o fim).
     *
     * @return list<array{k: string, label: string, peso: float, ativa: bool}>
     */
    public static function listaUnificada(Company $company): array
    {
        $legacy = $company->hasLegacyMetrics();
        $pesos = $company->pesos();
        $definicoes = $company->metricDefinitions()->withoutGlobalScopes()->where('company_id', $company->id)->get()
            ->reject(fn (MetricDefinition $d): bool => MetricDefinition::semScore($d->value_type))->keyBy('id');
        $sinal = fn (string $k): array => ['k' => $k, 'label' => Risco::ROTULOS[$k], 'peso' => (float) $pesos[$k], 'ativa' => $pesos[$k] > 0];
        $propria = fn (MetricDefinition $d): array => ['k' => 'custom:'.$d->id, 'label' => $d->label, 'peso' => (float) $d->weight, 'ativa' => $d->enabled && (float) $d->weight > 0];

        $itens = [];
        $vistos = [];
        foreach ($company->metric_weights ?? [] as $e) {
            $k = (string) ($e['k'] ?? '');
            if (isset($vistos[$k])) {
                continue;
            }
            if ($legacy && isset(Risco::PESOS[$k])) {
                $itens[] = $sinal($k);
                $vistos[$k] = true;
            } elseif (str_starts_with($k, 'custom:') && isset($definicoes[(int) substr($k, 7)])) {
                $itens[] = $propria($definicoes[(int) substr($k, 7)]);
                $vistos[$k] = true;
            }
        }
        foreach ($legacy ? array_keys($pesos) : [] as $k) {
            if (! isset($vistos[$k])) {
                $itens[] = $sinal($k);
                $vistos[$k] = true;
            }
        }
        foreach ($definicoes->sortByDesc(fn (MetricDefinition $d): float => (float) $d->weight) as $d) {
            if (isset($vistos['custom:'.$d->id])) {
                continue;
            }
            $item = $propria($d);
            $posicao = $item['ativa'] ? collect($itens)->search(fn (array $i): bool => ! $i['ativa'] || $i['peso'] < $item['peso']) : false;
            array_splice($itens, $posicao === false ? count($itens) : $posicao, 0, [$item]);
        }

        return $itens;
    }

    /** Depois de mudar o peso ou o estado de uma métrica em outra tela, ela volta a ser posicionada pelo peso na lista. */
    public static function esquecerPosicao(Company $company, int $definicaoId): void
    {
        if ($company->metric_weights === null) {
            return;
        }
        $company->update(['metric_weights' => array_values(array_filter($company->metric_weights, fn ($e): bool => ($e['k'] ?? '') !== 'custom:'.$definicaoId))]);
    }

    /** Liga ou desliga a IA da empresa. Desligada, o chat, o relatório e o configurador usam só respostas por regras, sem enviar nada a provedores externos. */
    public static function definirIa(Company $company, bool $ligada): void
    {
        $company->update(['chat_settings' => [...$company->chat(), 'enabled' => $ligada]]);
    }

    /**
     * Update: valida e grava. Pesos ou limiares novos disparam o recálculo do risco da empresa.
     *
     * @return bool se o risco foi recalculado
     */
    public static function salvar(Company $company, array $dados): bool
    {
        $legacy = $company->hasLegacyMetrics();
        $v = Validator::make($dados, [
            'metricas' => 'required|array|size:'.count(Risco::PESOS),
            'metricas.*.k' => 'required|distinct|in:'.implode(',', array_keys(Risco::PESOS)),
            'metricas.*.peso' => 'required|numeric|min:0|max:100',
            'limiares.critico' => 'required|integer|between:1,100',
            'limiares.alto' => 'required|integer|between:1,100|lt:limiares.critico',
            'limiares.medio' => 'required|integer|between:1,100|lt:limiares.alto',
            'prioridade' => 'required|integer|between:0,500',
            'ordem' => 'sometimes|array',
            'ordem.*' => 'string|max:40',
            'proprias' => 'sometimes|array',
            'proprias.*.id' => 'required|integer',
            'proprias.*.peso' => 'required|numeric|min:0|max:100',
            'proprias.*.ativa' => 'sometimes|boolean',
            'tema.primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tema.secondary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tema.font' => 'sometimes|in:'.implode(',', self::FONTES),
            'tema.brand' => 'nullable|string|max:40',
            'tema.logo' => 'nullable|string|max:255',
        ]);
        $v->after(function ($v) use ($dados, $company) {
            $primaria = $dados['tema']['primary'] ?? '';

            // botões primários levam texto branco: a cor precisa sustentar pelo menos 3:1 (componentes de interface)
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $primaria) && Tema::contrasteComBranco($primaria) < 3) {
                $v->errors()->add('tema.primary', 'A cor primária é clara demais: o texto branco dos botões ficaria ilegível. Escolha uma cor mais escura.');
            }

            $customWeight = isset($dados['proprias'])
                ? collect($dados['proprias'])->filter(fn ($m) => $m['ativa'] ?? true)->sum(fn ($m) => (float) ($m['peso'] ?? 0))
                : $company->metricDefinitions()->where('enabled', true)->sum('weight');
            if (array_sum(array_column($dados['metricas'] ?? [], 'peso')) + $customWeight <= 0) {
                $v->errors()->add('metricas', 'Pelo menos uma métrica precisa ter peso maior que zero.');
            }
        });
        $d = $v->validate();

        $pesos = array_map(fn ($m) => ['k' => $m['k'], 'peso' => (float) $m['peso']], array_values($d['metricas']));
        $limiares = array_map('intval', $d['limiares']);
        // != (não !==): 100 e 100.0 são o mesmo peso; a ordem da lista continua contando (desempate de sinais)
        $atuais = array_values(array_filter($company->metric_weights ?? [], fn ($e): bool => isset(Risco::PESOS[$e['k'] ?? ''])));
        $recalcular = ($legacy && $pesos != ($atuais ?: self::padrao())) || $limiares != $company->limiares();

        $mudouProprias = false;
        foreach ($d['proprias'] ?? [] as $m) {
            $definicao = $company->metricDefinitions()->withoutGlobalScopes()->where('company_id', $company->id)->find($m['id']);
            $peso = (float) $m['peso'];
            $ativa = (bool) ($m['ativa'] ?? true);
            if ($definicao && ! MetricDefinition::semScore($definicao->value_type) && ((float) $definicao->weight !== $peso || (bool) $definicao->enabled !== $ativa)) {
                $definicao->update(['weight' => $peso, 'enabled' => $ativa]);
                $mudouProprias = true;
            }
        }
        $recalcular = $recalcular || $mudouProprias;

        $settings = ['level_thresholds' => $limiares, 'theme' => $d['tema'], 'priority_balance' => (int) $d['prioridade']];
        $ordem = $d['ordem'] ?? null;
        if ($legacy || $ordem) {
            $sinais = collect($pesos)->keyBy('k');
            $proprias = collect($d['proprias'] ?? [])->keyBy(fn ($m) => 'custom:'.$m['id']);
            $lista = [];
            foreach ($ordem ?? [] as $k) {
                if ($legacy && isset($sinais[$k])) {
                    $lista[] = $sinais[$k];
                } elseif (isset($proprias[$k])) {
                    $lista[] = ['k' => $k, 'peso' => (float) $proprias[$k]['peso']];
                }
            }
            if ($legacy) {
                $lista = [...$lista, ...array_filter($pesos, fn ($p) => ! in_array($p['k'], array_column($lista, 'k'), true))];
                if (! $ordem) { // sem a lista unificada: preserva a posição já salva das métricas da empresa
                    $lista = [...$lista, ...array_filter($company->metric_weights ?? [], fn ($e) => str_starts_with((string) ($e['k'] ?? ''), 'custom:'))];
                }
            }
            $settings['metric_weights'] = array_values($lista);
        }
        $company->update($settings);

        if ($recalcular) {
            RiskService::recalcular($company);
        }

        return $recalcular;
    }

    /**
     * Pesos a partir da posição na lista de prioridade: a escala (a dos pesos informados ou, sem eles, a padrão
     * 20, 15, 15, 12...) distribuída na ordem dada, do maior para o menor. Desligadas ficam com peso 0 e não ocupam posição.
     *
     * @param  list<array{k: string, ativa?: bool, peso?: float|int}>  $metricas
     * @return list<array{k: string, peso: float|int}>
     */
    public static function pesosPorPosicao(array $metricas): array
    {
        $escala = collect($metricas)->filter(fn ($m) => $m['ativa'] ?? true)
            ->map(fn ($m) => ($m['peso'] ?? 0) > 0 ? (float) $m['peso'] : (Risco::PESOS[$m['k']] ?? 10))
            ->sortDesc()->values();
        $i = 0;

        $out = [];

        foreach (array_values($metricas) as $m) { // foreach (não array_map): o contador $i precisa avançar entre as métricas
            $out[] = ['k' => $m['k'], 'peso' => ($m['ativa'] ?? true) ? $escala[$i++] : 0];
        }

        return $out;
    }

    /**
     * Configuração recomendada pelos dados da própria carteira: métricas ordenadas pelo quanto separam cancelados de
     * retidos (as que não separam são desligadas) e cortes de nível calibrados para um alarme falso aceitável.
     * Precisa de evidência suficiente (poucos cancelamentos não calibram nada).
     */
    public static function aplicarConfiguracaoDosDados(Company $company): bool
    {
        $resumo = Backtest::resumo($company);

        if (! $resumo['evidencia_suficiente']) {
            return false;
        }

        $d = self::ler($company);
        $metricas = collect($d['metricas'])->map(fn ($m) => $m + ['sug' => $resumo['variaveis'][$m['k']]['peso_sugerido'] ?? 0])
            ->sortByDesc('sug')->values()->map(fn ($m) => ['k' => $m['k'], 'ativa' => $m['sug'] > 0])->all();
        $d['metricas'] = self::pesosPorPosicao($metricas);

        // os cortes dependem dos pesos novos: recalcula o backtest com eles antes de escolher
        $novos = collect($d['metricas'])->mapWithKeys(fn ($m) => [$m['k'] => $m['peso']])->all();
        $d['limiares'] = (new Backtest($novos))->limiaresSugeridos();

        return self::salvar($company, $d);
    }

    /** Configuração base após a primeira carga de dados: só se a empresa ainda não personalizou pesos nem cortes. */
    public static function aplicarBaseDosDados(Company $company): bool
    {
        if ($company->metric_weights !== null || $company->level_thresholds !== null || $company->metricDefinitions()->exists()) {
            return false;
        }

        return self::aplicarConfiguracaoDosDados($company);
    }

    /** Delete: remove as personalizações e volta ao padrão do sistema. */
    public static function restaurar(Company $company): void
    {
        $company->update(['metric_weights' => null, 'level_thresholds' => null, 'theme' => null, 'priority_balance' => null]);
        RiskService::recalcular($company);
    }

    private static function padrao(): array
    {
        return collect(Risco::PESOS)->map(fn ($p, $k) => ['k' => $k, 'peso' => (float) $p])->values()->all();
    }
}
