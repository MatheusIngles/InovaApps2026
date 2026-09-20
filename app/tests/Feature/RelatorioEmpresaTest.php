<?php

namespace Tests\Feature;

use App\Jobs\GerarRelatorioEmpresaJob;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_solicitacao_envia_relatorio_para_a_fila_sem_gerar_pdf_na_requisicao(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = $this->entrar();
        $empresa = $this->empresaComSinais();

        Livewire::test(RelatorioEmpresa::class, ['codigo' => $empresa->codigo])
            ->call('abrir')
            ->call('gerar')
            ->assertSee('Gerar relatório');

        Queue::assertPushed(GerarRelatorioEmpresaJob::class, fn (GerarRelatorioEmpresaJob $job): bool =>
            $job->companyId === $user->company_id && $job->userId === $user->id && $job->customerId === $empresa->id
            && count($job->indices) === count($empresa->sinais));
        $this->assertSame([], Storage::disk('local')->allFiles('relatorios'));
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_job_gera_pdf_notifica_e_download_fica_restrito_ao_usuario(): void
    {
        Storage::fake('local');
        $user = $this->entrar();
        $empresa = $this->empresaComSinais();
        $arquivoId = (string) Str::uuid();
        Http::fake(['localhost:11434/*' => Http::response(['message' => ['content' => '@@1@@ Análise local de teste.']])]);

        (new GerarRelatorioEmpresaJob($user->company_id, $user->id, $empresa->id, $arquivoId, [0], null))->handle();

        $caminho = GerarRelatorioEmpresaJob::caminho($user->company_id, $user->id, $arquivoId);
        Storage::disk('local')->assertExists($caminho);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($caminho));
        $this->assertSame('Relatório pronto', $user->notifications()->first()->data['title']);
        $this->get(route('relatorios.download', ['arquivo' => $arquivoId]))->assertOk()->assertDownload('relatorio.pdf');

        $outro = User::factory()->for($user->company)->create();
        $this->actingAs($outro)->get(route('relatorios.download', ['arquivo' => $arquivoId]))->assertNotFound();
    }

    public function test_job_gera_pdf_com_fallback_quando_ia_esta_indisponivel(): void
    {
        Storage::fake('local');
        $user = $this->entrar();
        $empresa = $this->empresaComSinais();
        $arquivoId = (string) Str::uuid();

        Http::fake(fn () => throw new ConnectionException('offline'));

        (new GerarRelatorioEmpresaJob($user->company_id, $user->id, $empresa->id, $arquivoId, [0], null))->handle();

        Storage::disk('local')->assertExists(GerarRelatorioEmpresaJob::caminho($user->company_id, $user->id, $arquivoId));
        $this->assertSame('Relatório pronto', $user->notifications()->first()->data['title']);
    }
}
