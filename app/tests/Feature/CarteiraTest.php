<?php

namespace Tests\Feature;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Livewire\AssistenteChat;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\CustomerNps;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Support\Assistente;
use Database\Seeders\CustomerDataSeeder;
use Database\Seeders\RiskAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\Llm\Llm;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CarteiraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CustomerDataSeeder::class);
        $this->seed(RiskAssessmentSeeder::class);
    }

    public function test_visitante_vai_para_login_e_login_tem_vlibras_no_painel(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/empresas')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/painel')->assertOk()->assertSee('vlibras', false);
    }

    public function test_base_importada_e_score_separa_cancelados_de_ativos(): void
    {
        $this->assertSame(80, Customer::count());
        $this->assertSame(22, Customer::where('status', 'Cancelado')->count());
        $this->assertSame(1295, CustomerMetric::count());
        $this->assertSame(422, CustomerNps::count());
        $this->assertSame(84, CustomerNps::where('answered', false)->whereNull('score')->count());
        $this->assertSame(80, RiskAssessment::count());
        $this->assertSame(23, CustomerMetric::whereNull('sla_percentage')->count());
        $this->assertSame(80, RiskAssessment::whereNull('risk_probability')->count());
        $this->assertSame(0, Customer::where('status', 'Cancelado')->whereHas('currentAssessment', fn ($query) => $query->whereColumn('risk_assessments.reference_month', '>=', 'customers.cancelled_at'))->count());
        $this->seed(CustomerDataSeeder::class);
        $this->seed(RiskAssessmentSeeder::class);
        $this->assertSame(80, Customer::count());
        $this->assertSame(80, RiskAssessment::count());
        $this->assertGreaterThan(50, Customer::dashboard()->where('customers.status', 'Cancelado')->get()->avg('score'));
        $this->assertLessThan(30, Customer::dashboard()->where('customers.status', 'Ativo')->get()->avg('score'));
    }

    public function test_grid_ordena_ativas_por_exposicao_e_canceladas_por_ultimo(): void
    {
        $status = Customer::ordenar(Customer::dashboard())->pluck('status');
        $this->assertSame($status->sortBy(fn ($s) => $s === 'Cancelado')->values()->all(), $status->all());
        $exp = Customer::ordenar(Customer::dashboard())->where('customers.status', 'Ativo')->pluck('exposicao');
        $this->assertSame($exp->sortDesc()->values()->all(), $exp->all());
    }

    public function test_telas_do_painel_renderizam(): void
    {
        $this->actingAs(User::factory()->create());
        $top = Customer::ordenar(Customer::dashboard())->first();

        $this->get('/empresas')->assertOk()->assertSee($top->nome);
        $this->get('/empresas/'.$top->codigo)->assertOk()->assertSee($top->sinais[0]['texto'])->assertSee('Chat com a IA')
            ->assertSee(EmpresaResource::getUrl('view', ['record' => $top->similares[0]['codigo']]));
        $this->get('/assistente')->assertOk();
        $this->get('/empresas/X999')->assertNotFound();
    }

    public function test_assistente_responde_carteira_e_empresa(): void
    {
        $top = Customer::ativas()->first();
        $this->assertStringContainsString($top->codigo, Assistente::responder('Quem devo ligar primeiro?'));
        $this->assertStringContainsString($top->nome, Assistente::responder('o que fazer?', $top->codigo));
        $this->assertStringContainsString($top->nome, Assistente::responder('por que '.$top->codigo.' está em risco?'));
    }

    public function test_raiz_redireciona_para_a_empresa_prioritaria(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertRedirect(EmpresaResource::getUrl('view', ['record' => Customer::ativas()->first()]));
    }

    public function test_chat_usa_ollama_com_contexto_da_empresa(): void
    {
        $this->actingAs(User::factory()->create());
        $top = Customer::ativas()->first();
        Http::fake(['localhost:11434/*' => Http::response(['message' => ['content' => 'Resposta local']])]);

        Livewire::test(AssistenteChat::class)->set('codigo', $top->codigo)->call('enviar', 'Por que está em risco?')
            ->assertSee('Resposta local')->assertSee('Modelo local');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/chat') && str_contains($r['messages'][0]['content'], $top->nome));
    }

    public function test_pergunta_complexa_escala_para_api_externa(): void
    {
        config(['llm.api.key' => 'k']);
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Resposta da API']]]]),
            'localhost:11434/*' => Http::response(['message' => ['content' => 'Resposta local']]),
        ]);

        $r = Llm::responder('sistema', [['role' => 'user', 'content' => 'Monte uma estratégia de retenção']]);
        $this->assertSame('api', $r['provedor']);
        $this->assertSame('ollama', Llm::responder('sistema', [['role' => 'user', 'content' => 'oi']])['provedor']);
    }

    public function test_sem_llm_o_chat_cai_para_as_regras(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake(fn () => throw new ConnectionException('offline'));

        Livewire::test(AssistenteChat::class)->call('enviar', 'Resumo da carteira')->assertSee('Respostas por regras');
    }
}
