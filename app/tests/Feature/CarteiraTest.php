<?php

namespace Tests\Feature;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use App\Models\User;
use App\Filament\Resources\Empresas\Pages\ViewEmpresa;
use App\Livewire\AssistenteChat;
use App\Support\Assistente;
use Database\Seeders\CarteiraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CarteiraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarteiraSeeder::class);
    }

    public function test_visitante_vai_para_login_e_login_tem_vlibras_no_painel(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/empresas')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('vlibras', false);
    }

    public function test_base_importada_e_score_separa_cancelados_de_ativos(): void
    {
        $this->assertSame(80, Empresa::count());
        $this->assertSame(22, Empresa::where('status', 'Cancelado')->count());
        $this->assertGreaterThan(50, Empresa::where('status', 'Cancelado')->avg('score'));
        $this->assertLessThan(30, Empresa::where('status', 'Ativo')->avg('score'));
    }

    public function test_grid_ordena_ativas_por_exposicao_e_canceladas_por_ultimo(): void
    {
        $status = Empresa::ordenar(Empresa::query())->pluck('status');
        $this->assertSame($status->sortBy(fn ($s) => $s === 'Cancelado')->values()->all(), $status->all());
        $exp = Empresa::ordenar(Empresa::query())->where('status', 'Ativo')->pluck('exposicao');
        $this->assertSame($exp->sortDesc()->values()->all(), $exp->all());
    }

    public function test_telas_do_painel_renderizam(): void
    {
        $this->actingAs(User::factory()->create());
        $top = Empresa::ordenar(Empresa::query())->first();

        $this->get('/empresas')->assertOk()->assertSee($top->nome);
        $this->get('/empresas/'.$top->codigo)->assertOk()->assertSee($top->sinais[0]['texto'])->assertSee('Chat da empresa')
            ->assertSee(EmpresaResource::getUrl('view', ['record' => $top->similares[0]['codigo']]));
        $this->get('/assistente')->assertOk();
        $this->get('/empresas/X999')->assertNotFound();
    }

    public function test_assistente_responde_carteira_e_empresa(): void
    {
        $top = Empresa::ativas()->first();
        $this->assertStringContainsString($top->codigo, Assistente::responder('Quem devo ligar primeiro?'));
        $this->assertStringContainsString($top->nome, Assistente::responder('o que fazer?', $top->codigo));
        $this->assertStringContainsString($top->nome, Assistente::responder('por que '.$top->codigo.' está em risco?'));
    }

    public function test_chat_livewire_e_modal_da_empresa(): void
    {
        $this->actingAs(User::factory()->create());
        $top = Empresa::ativas()->first();

        Livewire::test(AssistenteChat::class, ['seletor' => true])
            ->call('enviar', 'Quem devo ligar primeiro?')
            ->assertSee($top->codigo)->assertSet('pergunta', '');
        Livewire::test(ViewEmpresa::class, ['record' => $top->codigo])
            ->mountAction('chat')->assertActionMounted('chat');
    }
}
