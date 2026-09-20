<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Planilha;
use App\Models\Customer;
use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\Tema;
use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Define a empresa ativa a partir do usuário autenticado (nunca de dados da requisição) e amarra a sessão a ela:
 * se o company_id da sessão divergir do do usuário, a sessão é encerrada. Visitantes só recebem o tema (tenant da URL).
 */
class SetCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        $contexto = app(CompanyContext::class);

        if ($user = $request->user()) {
            $company = $user->company;
            $vinculada = $request->session()->get('company_id');

            if (! $company || ($vinculada !== null && $vinculada !== $company->id)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login');
            }

            $request->session()->put('company_id', $company->id);
            $contexto->set($company);
        } else {
            $company = TenantResolver::company($request); // só para o tema da tela de login
        }

        if ($company) {
            Tema::aplicar($company);
        }

        // empresa nova (sem dados): só a tela de planilha até a primeira carga
        if ($request->user() && $request->isMethod('GET') && ! $request->is('planilha', 'modelo/planilha*', 'livewire*', 'logout') && ! Customer::exists()) {
            return redirect(Planilha::getUrl());
        }

        return $next($request);
    }
}
