<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Login por e-mail e senha: a empresa é a do usuário (e-mail único), sem escolher tenant.
 * A sessão fica vinculada ao company_id do usuário.
 *
 * Além do limite por IP do Filament (5 por minuto), há um limite por e-mail: 10 erros em 15 minutos bloqueiam novas
 * tentativas naquela conta, mesmo vindo de IPs diferentes (força bruta distribuída).
 */
class Login extends BaseLogin
{
    private const TENTATIVAS_POR_EMAIL = 10;

    public function authenticate(): ?LoginResponse
    {
        $chave = 'login-email:'.sha1(mb_strtolower(trim((string) ($this->data['email'] ?? ''))));
        if (RateLimiter::tooManyAttempts($chave, self::TENTATIVAS_POR_EMAIL)) {
            throw ValidationException::withMessages(['data.email' => 'Muitas tentativas para este e-mail. Tente novamente em '.max(1, (int) ceil(RateLimiter::availableIn($chave) / 60)).' minuto(s).']);
        }

        try {
            $resposta = parent::authenticate();
        } catch (ValidationException $e) {
            RateLimiter::hit($chave, 900);

            throw $e;
        }
        RateLimiter::clear($chave);

        if ($resposta && ($user = auth()->user())) {
            session(['company_id' => $user->company_id]);
        }

        return $resposta;
    }
}
