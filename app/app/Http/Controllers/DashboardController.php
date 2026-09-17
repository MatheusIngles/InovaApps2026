<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Exibe o painel principal (Dashboard) do usuário autenticado.
     */
    public function index()
    {
        $user = Auth::user();
        $totalUsers = User::count();

        // Dados estatísticos de exemplo para preenchimento dos cards do Dashboard
        $stats = [
            'total_users' => $totalUsers,
            'user_role' => 'Administrador',
            'session_status' => 'Conectado',
            'last_login' => now()->format('d/m/Y H:i'),
        ];

        return view('dashboard', compact('user', 'stats'));
    }
}
