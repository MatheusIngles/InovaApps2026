<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Closure;
use Illuminate\Support\Collection;

/**
 * Empresa (tenant) ativa na requisição. Singleton: o middleware define a empresa do usuário
 * autenticado; sem definição explícita, cai para a empresa do usuário logado (cobre Livewire).
 */
class CompanyContext
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function current(): ?Company
    {
        return $this->company ??= auth()->user()?->company;
    }

    public function id(): ?int
    {
        return $this->current()?->id;
    }

    /** Todos os contextos (tenants) disponíveis, ex.: para o seletor do login. */
    public function listContexts(): Collection
    {
        return Company::orderBy('name')->get(['id', 'name', 'slug']);
    }

    /** Executa $fn dentro do contexto de $company (seeders, comandos, filas) e restaura o anterior. */
    public function within(Company $company, Closure $fn): mixed
    {
        $anterior = $this->company;
        $this->company = $company;

        try {
            return $fn();
        } finally {
            $this->company = $anterior;
        }
    }
}
