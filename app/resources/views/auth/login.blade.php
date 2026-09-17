@extends('layouts.guest', ['title' => 'Entrar na Plataforma'])

@section('content')
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-white">Acesse sua conta</h1>
        <p class="text-sm text-slate-400 mt-1">Informe suas credenciais para entrar no sistema</p>
    </div>

    {{-- Card de Credenciais Demonstrativas para Avaliação --}}
    <div class="mb-6 p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/25 flex items-center justify-between gap-3">
        <div class="text-xs">
            <span class="font-semibold text-indigo-300 block">💡 Credenciais de Teste:</span>
            <span class="text-slate-300 font-mono">admin@inova.com</span> | <span class="text-slate-300 font-mono">senha123</span>
        </div>
        <button type="button" 
                onclick="fillDemoCredentials()"
                class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-600/30 hover:bg-indigo-600 text-indigo-200 hover:text-white transition-colors border border-indigo-500/40 whitespace-nowrap">
            Preencher
        </button>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
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

        {{-- Campo Senha --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-xs font-medium text-slate-300">Senha de Acesso</label>
                <a href="{{ route('password.request') }}" class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors">
                    Esqueceu a senha?
                </a>
            </div>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required
                       placeholder="••••••••"
                       class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                <button type="button" 
                        onclick="togglePasswordVisibility('password')"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition-colors">
                    <svg class="w-4 h-4" id="eye-icon-password" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
            @error('password')
                <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        {{-- Lembrar-me --}}
        <div class="flex items-center">
            <input type="checkbox" 
                   id="remember" 
                   name="remember" 
                   class="w-4 h-4 rounded border-slate-800 bg-slate-950 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900 cursor-pointer">
            <label for="remember" class="ml-2 text-xs text-slate-400 select-none cursor-pointer">
                Permanecer conectado neste dispositivo
            </label>
        </div>

        {{-- Botão Principal de Login --}}
        <button type="submit" 
                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-sm font-semibold shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/35 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
            Acessar Plataforma
        </button>
    </form>

    {{-- Link para Cadastro --}}
    <div class="mt-6 pt-5 border-t border-slate-800/80 text-center">
        <p class="text-xs text-slate-400">
            Ainda não possui uma conta? 
            <a href="{{ route('register') }}" class="font-semibold text-indigo-400 hover:text-indigo-300 transition-colors ml-1">
                Cadastre-se aqui
            </a>
        </p>
    </div>
@endsection

@push('scripts')
<script>
    function fillDemoCredentials() {
        document.getElementById('email').value = 'admin@inova.com';
        document.getElementById('password').value = 'senha123';
    }

    function togglePasswordVisibility(fieldId) {
        const input = document.getElementById(fieldId);
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }
</script>
@endpush
