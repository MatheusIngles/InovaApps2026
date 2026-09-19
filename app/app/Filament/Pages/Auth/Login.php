<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Login por e-mail e senha: a empresa é a do usuário (e-mail único), sem escolher tenant.
 * A sessão fica vinculada ao company_id do usuário.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $resposta = parent::authenticate();

        if ($resposta && ($user = auth()->user())) {
            session(['company_id' => $user->company_id]);
        }

        return $resposta;
    }
}
