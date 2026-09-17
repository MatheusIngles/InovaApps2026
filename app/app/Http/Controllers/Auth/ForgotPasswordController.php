<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Exibe a tela de solicitação de recuperação de senha.
     */
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Gera o token de recuperação de senha e link de reset.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'O campo e-mail é obrigatório.',
            'email.email' => 'Por favor, informe um endereço de e-mail válido.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Não encontramos nenhum usuário cadastrado com este e-mail.',
            ]);
        }

        $token = Str::random(64);

        // Atualiza ou insere o token na tabela password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => now(),
            ]
        );

        $resetUrl = route('password.reset', ['token' => $token, 'email' => $request->email]);

        return back()->with([
            'status' => 'Um link de recuperação foi gerado com sucesso!',
            'demo_reset_link' => $resetUrl,
        ]);
    }

    /**
     * Exibe o formulário para definição da nova senha.
     */
    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.reset-password', [
            'token' => $token ?? $request->token,
            'email' => $request->email,
        ]);
    }

    /**
     * Redefine a senha do usuário com base no token validado.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
        ], [
            'token.required' => 'Token de validação ausente ou inválido.',
            'email.required' => 'O campo e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'O campo nova senha é obrigatório.',
            'password.min' => 'A nova senha deve ter no mínimo 6 caracteres.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['token' => 'Este link de recuperação é inválido ou já expirou.']);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Usuário não encontrado.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')
            ->with('success', 'Sua senha foi redefinida com sucesso! Você já pode entrar com sua nova senha.');
    }
}
