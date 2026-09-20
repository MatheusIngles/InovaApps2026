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
        $middleware->append(CabecalhosDeSeguranca::class); // global: vale também para as rotas do painel Filament
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
