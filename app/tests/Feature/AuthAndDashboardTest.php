<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa se a rota raiz redireciona para login.
     */
    public function test_guest_is_redirected_to_login_from_root(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('login'));
    }

    /**
     * Testa se a tela de login carrega normalmente.
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertSee('Acesse sua conta');
        $response->assertSee('admin@inova.com');
    }

    /**
     * Testa login com credenciais inválidas.
     */
    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'teste@inova.com',
            'password' => Hash::make('senha123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * Testa login com credenciais corretas.
     */
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'teste@inova.com',
            'password' => Hash::make('senha123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'senha123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Testa o cadastro de novo usuário.
     */
    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Novo Aluno Inova',
            'email' => 'aluno@inova.com',
            'password' => 'senhaSegura123',
            'password_confirmation' => 'senhaSegura123',
            'terms' => 'on',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'aluno@inova.com']);
    }

    /**
     * Testa solicitação e redefinição de senha.
     */
    public function test_forgot_and_reset_password_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@inova.com',
            'password' => Hash::make('senhaVelha123'),
        ]);

        // 1. Solicita link de recuperação
        $response = $this->post(route('password.email'), [
            'email' => 'resetme@inova.com',
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'resetme@inova.com']);

        $token = DB::table('password_reset_tokens')->where('email', 'resetme@inova.com')->value('token');

        // 2. Acessa tela de redefinição
        $response = $this->get(route('password.reset', ['token' => $token, 'email' => 'resetme@inova.com']));
        $response->assertStatus(200);

        // 3. Atualiza a senha
        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'resetme@inova.com',
            'password' => 'novaSenha456',
            'password_confirmation' => 'novaSenha456',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        // 4. Valida se o usuário consegue logar com a nova senha
        $this->post(route('login'), [
            'email' => 'resetme@inova.com',
            'password' => 'novaSenha456',
        ]);

        $this->assertAuthenticated();
    }

    /**
     * Testa se o Dashboard é bloqueado para não-autenticados.
     */
    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Testa visualização do Dashboard para usuário autenticado.
     */
    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Matheus Desenvolvedor',
            'email' => 'matheus@inova.com',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Matheus Desenvolvedor');
        $response->assertSee('Dashboard');
        $response->assertSee('vlibras');
    }

    /**
     * Testa logout.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    /**
     * Testa se o widget VLibras e seus scripts estão presentes em TODAS as telas.
     */
    public function test_vlibras_is_present_on_all_screens(): void
    {
        $user = User::factory()->create();

        $screens = [
            'login' => $this->get(route('login')),
            'cadastro' => $this->get(route('register')),
            'recuperar-senha' => $this->get(route('password.request')),
            'redefinir-senha' => $this->get(route('password.reset', ['token' => 'dummy-token'])),
            'dashboard' => $this->actingAs($user)->get(route('dashboard')),
        ];

        foreach ($screens as $name => $response) {
            $response->assertStatus(200);
            $response->assertSee('vw class="enabled"', false);
            $response->assertSee('vw-access-button', false);
            $response->assertSee('vw-plugin-wrapper', false);
            $response->assertSee('https://vlibras.gov.br/app/vlibras-plugin.js', false);
            $response->assertSee("new window.VLibras.Widget('https://vlibras.gov.br/app')", false);
        }
    }
}

