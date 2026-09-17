@extends('layouts.guest', ['title' => 'Redefinir Senha'])

@section('content')
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-white">Nova senha</h1>
        <p class="text-sm text-slate-400 mt-1">Crie uma nova senha segura para acessar sua conta</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        {{-- Token de Redefinição Oculto --}}
        <input type="hidden" name="token" value="{{ $token }}">

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
                       value="{{ $email ?? old('email') }}" 
                       required 
                       placeholder="seu.email@exemplo.com"
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
            </div>
            @error('email')
                <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        {{-- Campo Nova Senha --}}
        <div>
            <label for="password" class="block text-xs font-medium text-slate-300 mb-1.5">Nova Senha</label>
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
                       autofocus
                       placeholder="••••••••"
                       class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                <button type="button" 
                        onclick="togglePasswordVisibility('password')"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
            @error('password')
                <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        {{-- Campo Confirmação da Nova Senha --}}
        <div>
            <label for="password_confirmation" class="block text-xs font-medium text-slate-300 mb-1.5">Confirmar Nova Senha</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <input type="password" 
                       id="password_confirmation" 
                       name="password_confirmation" 
                       required
                       placeholder="••••••••"
                       class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-950/60 border border-slate-800 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                <button type="button" 
                        onclick="togglePasswordVisibility('password_confirmation')"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Botão de Submissão --}}
        <button type="submit" 
                class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-sm font-semibold shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/35 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
            Salvar Nova Senha
        </button>
    </form>

    {{-- Voltar ao Login --}}
    <div class="mt-6 pt-5 border-t border-slate-800/80 text-center">
        <a href="{{ route('login') }}" class="text-xs font-semibold text-slate-400 hover:text-indigo-400 transition-colors">
            Cancelar e voltar ao login
        </a>
    </div>
@endsection

@push('scripts')
<script>
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
