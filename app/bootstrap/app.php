<?php

use App\Http\Middleware\CabecalhosDeSeguranca;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // O login é fornecido pelo painel Filament em /login, mas a rota
        // não possui o nome "login" esperado pelo middleware auth padrão.
        $middleware->redirectGuestsTo('/login');
        // Atrás de um proxy reverso com HTTPS (Nginx Proxy Manager, Cloudflare...): confia nos cabeçalhos X-Forwarded-* só de
        // proxies da rede privada (ou dos IPs em TRUSTED_PROXIES; "*" confia em qualquer origem, use só se a porta do app não é pública).
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES') === '*' ? '*' : (env('TRUSTED_PROXIES') ? array_map('trim', explode(',', env('TRUSTED_PROXIES'))) : ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']),
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->append(CabecalhosDeSeguranca::class); // global: vale também para as rotas do painel Filament
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
