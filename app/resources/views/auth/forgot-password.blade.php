@extends('layouts.guest', ['title' => 'Recuperar Senha'])

@section('content')
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-white">Recuperação de senha</h1>
        <p class="text-sm text-slate-400 mt-1">Informe seu e-mail para receber o link de redefinição</p>
    </div>

    {{-- Link de Demonstração em Ambiente Local --}}
    @if (session('demo_reset_link'))
        <div class="mb-5 p-3.5 rounded-xl bg-indigo-500/15 border border-indigo-500/30 text-xs space-y-2">
            <div class="flex items-center gap-2 text-indigo-300 font-semibold">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                <span>Link de Redefinição Direto (Ambiente de Teste):</span>
            </div>
            <p class="text-slate-300 text-[11px]">
                Como estamos em ambiente local/demonstração sem envio real de e-mail, utilize o atalho abaixo:
            </p>
            <a href="{{ session('demo_reset_link') }}" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-500 transition-colors shadow-sm">
                Redefinir Senha Agora &rarr;
            </a>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        {{-- Campo E-mail --}}
        <div>
            <label for="email" class="block text-xs font-medium text-slate-300 mb-1.5">Endereço de E-mail</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                    </svg>
                </div>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       required 
                       autofocus
                       placeholder="seu.email@exemplo.com"
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>
            @error('email')
                <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        {{-- Botão de Submissão --}}
        <button type="submit" 
                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-sm font-semibold shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/35 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
            Gerar Link de Recuperação
        </button>
    </form>

    {{-- Voltar ao Login --}}
    <div class="mt-6 pt-5 border-t border-slate-800/80 text-center">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-indigo-400 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Voltar para a tela de login
        </a>
    </div>
@endsection
