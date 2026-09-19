<?php

namespace App\Support\Tenancy;

use App\Models\Company;
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
        ];
    }

    /**
     * Update: valida e grava. Pesos ou limiares novos disparam o recálculo do risco da empresa.
     *
     * @return bool se o risco foi recalculado
     */
    public static function salvar(Company $company, array $dados): bool
    {
        $v = Validator::make($dados, [
            'metricas' => 'required|array|size:'.count(Risco::PESOS),
            'metricas.*.k' => 'required|in:'.implode(',', array_keys(Risco::PESOS)),
            'metricas.*.peso' => 'required|numeric|min:0|max:100',
            'limiares.critico' => 'required|integer|between:1,100',
            'limiares.alto' => 'required|integer|between:1,100|lt:limiares.critico',
            'limiares.medio' => 'required|integer|between:1,100|lt:limiares.alto',
            'prioridade' => 'required|integer|between:0,500',
            'tema.primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tema.secondary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tema.font' => 'sometimes|in:'.implode(',', self::FONTES),
            'tema.brand' => 'nullable|string|max:40',
            'tema.logo' => 'nullable|string|max:255',
        ]);
        $v->after(function ($v) use ($dados) {
            $primaria = $dados['tema']['primary'] ?? '';

            // botões primários levam texto branco: a cor precisa sustentar pelo menos 3:1 (componentes de interface)
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $primaria) && Tema::contrasteComBranco($primaria) < 3) {
                $v->errors()->add('tema.primary', 'A cor primária é clara demais: o texto branco dos botões ficaria ilegível. Escolha uma cor mais escura.');
            }

            if (array_sum(array_column($dados['metricas'] ?? [], 'peso')) <= 0) {
                $v->errors()->add('metricas', 'Pelo menos uma métrica precisa ter peso maior que zero.');
            }
        });
        $d = $v->validate();

        $pesos = array_map(fn ($m) => ['k' => $m['k'], 'peso' => (float) $m['peso']], array_values($d['metricas']));
        $limiares = array_map('intval', $d['limiares']);
        // != (não !==): 100 e 100.0 são o mesmo peso; a ordem da lista continua contando (desempate de sinais)
        $recalcular = $pesos != ($company->metric_weights ?? self::padrao()) || $limiares != $company->limiares();

        $company->update(['metric_weights' => $pesos, 'level_thresholds' => $limiares, 'theme' => $d['tema'], 'priority_balance' => (int) $d['prioridade']]);

        if ($recalcular) {
            RiskService::recalcular($company);
        }

        return $recalcular;
    }

    /**
     * Pesos a partir da posição na lista de prioridade: a escala padrão (20, 15, 15, 12...) distribuída na ordem dada.
     * Métricas desligadas ficam com peso 0 e não ocupam posição.
     *
     * @param  list<array{k: string, ativa?: bool}>  $metricas
     * @return list<array{k: string, peso: float|int}>
     */
    public static function pesosPorPosicao(array $metricas): array
    {
        $escala = collect(Risco::PESOS)->sortDesc()->values();
        $i = 0;

        $out = [];

        foreach (array_values($metricas) as $m) { // foreach (não array_map): o contador $i precisa avançar entre as métricas
            $out[] = ['k' => $m['k'], 'peso' => ($m['ativa'] ?? true) ? $escala[$i++] : 0];
        }

        return $out;
    }

    /** Reordena as métricas pela evidência dos dados (backtest); as que não separam cancelados de retidos são desligadas. */
    public static function aplicarOrdemDosDados(Company $company): bool
    {
        $variaveis = Backtest::resumo($company)['variaveis'];
        $d = self::ler($company);
        $metricas = collect($d['metricas'])->map(fn ($m) => $m + ['sug' => $variaveis[$m['k']]['peso_sugerido'] ?? 0])
            ->sortByDesc('sug')->values()->map(fn ($m) => ['k' => $m['k'], 'ativa' => $m['sug'] > 0])->all();
        $d['metricas'] = self::pesosPorPosicao($metricas);

        return self::salvar($company, $d);
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
