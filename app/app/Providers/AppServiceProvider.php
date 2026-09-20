<?php

namespace App\Providers;

use App\Support\FilaDeAtendimento;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FilaDeAtendimento::class);
        $this->app->singleton(CompanyContext::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rede de segurança: se APP_URL é https, todo link e asset sai em https, mesmo que o proxy não mande X-Forwarded-Proto.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
