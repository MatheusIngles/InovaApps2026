<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - InovaApps 2026
|--------------------------------------------------------------------------
*/

// Rota inicial: Redireciona para o dashboard se logado, ou para o login se visitante
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Rotas de Visitantes (Não autenticados)
Route::middleware('guest')->group(function () {
    // Autenticação / Login
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Cadastro / Registro
    Route::get('/cadastro', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/cadastro', [RegisterController::class, 'register']);

    // Recuperação de Senha
    Route::get('/recuperar-senha', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/recuperar-senha', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/redefinir-senha/{token?}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/redefinir-senha', [ForgotPasswordController::class, 'reset'])->name('password.update');
});

// Rotas Protegidas (Exigem autenticação)
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Dashboard Principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
