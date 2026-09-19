<?php

namespace Tests\Feature;

use App\Livewire\RelatorioEmpresa;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Database\Seeders\CustomerDataSeeder;
use Database\Seeders\RiskAssessmentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RelatorioEmpresaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
        $this->seed(CustomerDataSeeder::class);
        $this->seed(RiskAssessmentSeeder::class);
        app(CompanyContext::class)->set(Company::firstWhere('slug', 'demo'));
    }

    /** Usuário da empresa demo (a que tem a base do desafio) autenticado. */
    private function entrar(): User
    {
        $user = User::factory()->for(Company::firstWhere('slug', 'demo'))->create();
        $this->actingAs($user);

        return $user;
    }

    /** Empresa ativa com ao menos um sinal, para exercitar a seleção de pontos críticos. */
    private function empresaComSinais(): Customer
    {
        $empresa = Customer::ativas()->first(fn (Customer $c) => count($c->sinais) > 0);
        $this->assertNotNull($empresa, 'Precisa de uma empresa ativa com sinais para este teste.');

        return $empresa;
    }

    public function test_botao_de_relatorio_aparece_na_pagina_da_empresa(): void
    {
        $this->entrar();
        $top = Customer::ativas()->first();

        $this->get('/empresas/'.$top->codigo)->assertOk()->assertSee('Gerar relatório');
    }

    public function test_abrir_seleciona_todos_os_sinais_por_padrao(): void
    {
        $this->entrar();
        $empresa = $this->empresaComSinais();

        Livewire::test(RelatorioEmpresa::class, ['codigo' => $empresa->codigo])
            ->call('abrir')
            ->assertSet('data.sinais', array_keys($empresa->sinais));
    }

    public function test_gera_pdf_com_analise_da_ia_para_os_sinais_escolhidos(): void
    {
        $this->entrar();
        $empresa = $this->empresaComSinais();

        Http::fake([
            'localhost:11434/*' => Http::response(['message' => ['content' => 'Análise local de teste.']]),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Análise da API de teste.']]]]),
        ]);

        Livewire::test(RelatorioEmpresa::class, ['codigo' => $empresa->codigo])
            ->call('abrir')
            ->call('gerar')
            ->assertFileDownloaded("relatorio-{$empresa->codigo}.pdf");
    }

    public function test_gera_pdf_com_fallback_por_regras_quando_ia_esta_indisponivel(): void
    {
        $this->entrar();
        $empresa = $this->empresaComSinais();

        Http::fake(fn () => throw new ConnectionException('offline'));

        Livewire::test(RelatorioEmpresa::class, ['codigo' => $empresa->codigo])
            ->call('abrir')
            ->call('gerar')
            ->assertFileDownloaded("relatorio-{$empresa->codigo}.pdf");
    }
}
