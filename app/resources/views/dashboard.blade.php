@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<div class="space-y-8">
    {{-- Banner de Boas-Vindas com Gradiente Elegante --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-900/60 via-slate-900 to-purple-950/50 p-6 sm:p-8 border border-indigo-500/20 shadow-xl backdrop-blur-xl">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs font-medium">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    Ambiente Base Pronto
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                    Olá, {{ auth()->user()->name }}! 👋
                </h1>
                <p class="text-sm text-slate-300 max-w-2xl leading-relaxed">
                    Seu sistema de autenticação e layout base (Navbar + Sidebar) estão totalmente operacionais.
                    Esta é a estrutura inicial para a sua aplicação no <strong class="text-indigo-300">InovaApps 2026</strong>.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="#quick-guide" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/30 transition-all">
                    Ver Informações da Base
                </a>
            </div>
        </div>

        {{-- Detalhes decorativos de fundo --}}
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    {{-- Cards de Indicadores / Métricas --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        {{-- Card 1: Usuários Cadastrados --}}
        <div class="p-5 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-indigo-500/30 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-400">Usuários no Sistema</span>
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-bold text-white">{{ $stats['total_users'] ?? 1 }}</div>
                <p class="text-xs text-emerald-400 flex items-center gap-1 mt-1">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Autenticação ativa
                </p>
            </div>
        </div>

        {{-- Card 2: Perfil de Acesso --}}
        <div class="p-5 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-purple-500/30 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-400">Nível de Permissão</span>
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-bold text-white">{{ $stats['user_role'] ?? 'Membro' }}</div>
                <p class="text-xs text-purple-400 mt-1">Acesso irrestrito</p>
            </div>
        </div>

        {{-- Card 3: Sessão Segura --}}
        <div class="p-5 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-emerald-500/30 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-400">Estado da Sessão</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-bold text-white">{{ $stats['session_status'] ?? 'Online' }}</div>
                <p class="text-xs text-slate-400 mt-1">IP protegido e criptografado</p>
            </div>
        </div>

        {{-- Card 4: Stack Tecnológico --}}
        <div class="p-5 rounded-2xl bg-slate-900/70 border border-slate-800 hover:border-sky-500/30 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-400">Framework & UI</span>
                <div class="w-10 h-10 rounded-xl bg-sky-500/10 flex items-center justify-center text-sky-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-bold text-white">Laravel 12</div>
                <p class="text-xs text-sky-400 mt-1">Tailwind CSS v4 + VLibras</p>
            </div>
        </div>
    </div>

    {{-- Seção: Arquitetura & Prontidão para o Tema --}}
    <div id="quick-guide" class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                Status dos Módulos do Sistema
            </h2>
            <span class="text-xs text-slate-400">InovaApps 2026</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Módulo 1: Autenticação --}}
            <div class="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 space-y-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/15 text-indigo-400 flex items-center justify-center font-bold text-sm">
                    01
                </div>
                <h3 class="text-base font-semibold text-white">Sistema de Autenticação</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Telas de <strong>Login</strong>, <strong>Cadastro</strong> e <strong>Recuperação de Senha</strong> implementadas nativamente com validações completas em português e proteção CSRF.
                </p>
                <div class="pt-2 flex items-center gap-2 text-xs text-emerald-400 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    100% Funcional
                </div>
            </div>

            {{-- Módulo 2: Layout Base --}}
            <div class="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 space-y-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/15 text-purple-400 flex items-center justify-center font-bold text-sm">
                    02
                </div>
                <h3 class="text-base font-semibold text-white">Layout Base (Navbar + Sidebar)</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Menu lateral retrátil para desktop e gaveta deslizante para dispositivos móveis, além de barra de navegação com perfil de usuário, botão de logout e status.
                </p>
                <div class="pt-2 flex items-center gap-2 text-xs text-emerald-400 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Totalmente Responsivo
                </div>
            </div>

            {{-- Módulo 3: Acessibilidade VLibras --}}
            <div class="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 space-y-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center font-bold text-sm">
                    03
                </div>
                <h3 class="text-base font-semibold text-white">Acessibilidade Oficial</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Widget de Libras do Governo Federal (<strong>VLibras</strong>) integrado e carregado tanto na área pública quanto dentro do painel autenticado.
                </p>
                <div class="pt-2 flex items-center gap-2 text-xs text-emerald-400 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Acessibilidade Ativa
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
