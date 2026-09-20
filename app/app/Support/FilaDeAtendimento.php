<?php

namespace App\Support;

use App\Models\Customer;

/** Posição de cada cliente ativo na fila de atendimento (1 = primeiro), calculada uma vez por requisição. */
class FilaDeAtendimento
{
    /** @var array<string, int>|null código => posição */
    private ?array $posicoes = null;

    public function posicao(Customer $cliente): ?int
    {
        $this->posicoes ??= Customer::ativas()->pluck('codigo')->flip()->map(fn (int $i): int => $i + 1)->all();

        return $this->posicoes[$cliente->codigo] ?? null;
    }
}
