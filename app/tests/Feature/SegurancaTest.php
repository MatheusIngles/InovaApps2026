<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Livewire\ImportarPlanilha;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class SegurancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_bloqueia_a_conta_apos_10_erros_mesmo_com_a_senha_certa(): void
    {
        $user = User::factory()->for(Company::factory()->create())->create(['password' => 'segredo123']);
        $chave = 'login-email:'.sha1(mb_strtolower($user->email));
        foreach (range(1, 10) as $i) {
            RateLimiter::hit($chave, 900);
        }

        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'segredo123'])->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest();

        RateLimiter::clear($chave);
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'segredo123'])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_respostas_web_trazem_cabecalhos_de_seguranca(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_caminho_do_arquivo_enviado_nao_pode_ser_alterado_pelo_navegador(): void
    {
        $this->actingAs(User::factory()->for(Company::factory()->create())->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(ImportarPlanilha::class)->set('caminho', 'imports/1/de-outra-empresa.csv');
    }

    private function esquemaDoCss(string $origem): ?string
    {
        $html = $this->withServerVariables(['REMOTE_ADDR' => $origem])->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/login')->assertOk()->getContent();

        return preg_match('#(https?)://[^\s"]*css/panel\.css#', $html, $m) ? $m[1] : null;
    }

    public function test_atras_de_proxy_da_rede_privada_os_assets_saem_em_https(): void
    {
        $this->assertSame('https', $this->esquemaDoCss('192.168.0.5'));
    }

    /** Origem pública: o X-Forwarded-Proto é ignorado (não dá para forjar HTTPS nem o IP de quem acessa). */
    public function test_cabecalho_de_proxy_de_origem_publica_e_ignorado(): void
    {
        $this->assertSame('http', $this->esquemaDoCss('203.0.113.7'));
    }
}
