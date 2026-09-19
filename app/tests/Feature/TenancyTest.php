<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Planilha;
use App\Filament\Widgets\BacktestWidget;
use App\Filament\Widgets\FilaTable;
use App\Filament\Widgets\KpisWidget;
use App\Filament\Widgets\NiveisChart;
use App\Filament\Widgets\SegmentosChart;
use App\Filament\Widgets\TendenciaChart;
use App\Livewire\AssistenteChat;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
        $this->b = Company::factory()->create(['slug' => 'beta', 'name' => 'Beta']);
    }

    private function cliente(Company $company, string $codigo): Customer
    {
        return app(CompanyContext::class)->within($company, fn () => Customer::factory()->create(['company_id' => $company->id, 'external_code' => $codigo]));
    }

    public function test_login_por_email_e_senha_vincula_a_sessao_a_empresa_do_usuario(): void
    {
        $user = User::factory()->for($this->a)->create(['password' => 'segredo123']);

        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'errada'])->call('authenticate')->assertHasFormErrors();
        $this->assertGuest();

        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'segredo123'])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($this->a->id, session('company_id'));
    }

    public function test_login_nao_tem_seletor_de_empresa_mas_aplica_o_tema_do_tenant_da_url(): void
    {
        $this->b->update(['theme' => ['secondary' => '#115e59']]);

        $this->get('/login')->assertOk()->assertDontSee('Selecione uma opção');
        $this->get('/login?empresa=beta')->assertOk()->assertSee('Beta')->assertSee('#115e59', false);
    }

    public function test_cadastro_cria_empresa_e_primeiro_usuario_e_vai_para_a_planilha(): void
    {
        $dados = ['empresa' => 'Acme', 'name' => 'Ana', 'email' => 'ana@acme.test', 'password' => 'segredo123', 'passwordConfirmation' => 'segredo123'];

        Livewire::test(Register::class)->fillForm($dados)->call('register')->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'ana@acme.test');
        $this->assertSame('Acme', $user->company->name);
        $this->assertSame('acme-2', $user->company->slug); // "acme" já existe: slug único
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertRedirect(Planilha::getUrl()); // empresa nova, sem dados
    }

    public function test_usuario_so_enxerga_clientes_da_propria_empresa(): void
    {
        $this->cliente($this->a, 'C001');
        $this->cliente($this->b, 'C001'); // mesmo código em outra empresa é permitido
        $this->cliente($this->b, 'C002');

        $this->actingAs(User::factory()->for($this->a)->create());

        $this->get('/empresas/C001')->assertOk();
        $this->get('/empresas/C002')->assertNotFound(); // cliente de outra empresa
        $this->assertSame(['C001'], Customer::pluck('external_code')->all());
    }

    public function test_sessao_vinculada_a_outra_empresa_e_encerrada(): void
    {
        $user = User::factory()->for($this->a)->create();

        $this->actingAs($user)->withSession(['company_id' => $this->b->id])->get('/planilha')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_chat_e_isolado_por_empresa_e_por_usuario(): void
    {
        $ua = User::factory()->for($this->a)->create();
        $ub = User::factory()->for($this->b)->create();
        app(CompanyContext::class)->within($this->a, fn () => ChatMessage::create(['user_id' => $ua->id, 'role' => 'user', 'content' => 'segredo da Acme']));
        app(CompanyContext::class)->within($this->b, fn () => ChatMessage::create(['user_id' => $ub->id, 'role' => 'user', 'content' => 'segredo da Beta']));

        $this->actingAs($ub);
        app(CompanyContext::class)->set($this->b);
        $this->assertSame(['segredo da Beta'], ChatMessage::pluck('content')->all());
        Livewire::test(AssistenteChat::class)->assertSee('segredo da Beta')->assertDontSee('segredo da Acme');
    }

    public function test_contexto_lista_empresas_e_executa_dentro_de_um_contexto(): void
    {
        $ctx = app(CompanyContext::class);

        $this->assertEqualsCanonicalizing(['Acme', 'Beta'], $ctx->listContexts()->pluck('name')->all());
        $this->assertSame($this->a->id, $ctx->within($this->a, fn () => $ctx->id()));
        $this->assertNull($ctx->id()); // contexto anterior restaurado
    }

    public function test_painel_de_empresa_sem_dados_renderiza(): void
    {
        $this->actingAs(User::factory()->for($this->a)->create());

        $this->get('/painel')->assertOk();
        foreach ([KpisWidget::class, BacktestWidget::class, NiveisChart::class, SegmentosChart::class, TendenciaChart::class, FilaTable::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }
    }

    public function test_usuario_sem_empresa_nao_acessa_o_painel(): void
    {
        $this->actingAs(User::factory()->create(['company_id' => null]))->get('/planilha')->assertForbidden();
    }
}
