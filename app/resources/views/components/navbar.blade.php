<header class="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-slate-800/80 bg-slate-900/80 px-4 sm:px-6 backdrop-blur-xl transition-all duration-200">
    <div class="flex items-center gap-3">
        {{-- Botão de Alternância da Sidebar (Mobile e Desktop) --}}
        <button type="button" 
                id="sidebar-toggle-btn"
                onclick="toggleSidebar()"
                class="inline-flex items-center justify-center p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
                title="Alternar menu lateral">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        {{-- Logotipo Compacto / Título da Página --}}
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hidden sm:inline-block">
                InovaApps 2026
            </span>
            <span class="text-sm font-medium text-slate-300 hidden md:inline-block">/</span>
            <span class="text-sm font-semibold text-white tracking-tight">
                {{ $title ?? 'Painel Principal' }}
            </span>
        </div>
    </div>

    {{-- Lado Direito: Status, Notificações e Perfil --}}
    <div class="flex items-center gap-2 sm:gap-4">
        {{-- Badge de Status do Sistema --}}
        <div class="hidden lg:flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Sistema Online
        </div>

        {{-- Botão de Notificações com Badge --}}
        <div class="relative">
            <button type="button" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors relative">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-indigo-500"></span>
            </button>
        </div>

        {{-- Dropdown do Usuário --}}
        <div class="relative" id="user-menu-container">
            <button type="button" 
                    id="user-menu-button" 
                    onclick="toggleUserMenu()"
                    class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-slate-800/80 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white font-bold text-sm shadow-sm border border-indigo-400/30">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="text-left hidden md:block">
                    <div class="text-sm font-medium text-white leading-none">{{ auth()->user()->name ?? 'Usuário' }}</div>
                    <div class="text-xs text-slate-400 mt-0.5 leading-none">{{ auth()->user()->email ?? '' }}</div>
                </div>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200 hidden sm:block" id="user-menu-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {{-- Menu Suspenso --}}
            <div id="user-menu-dropdown" 
                 class="hidden absolute right-0 mt-2 w-56 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl py-2 z-50 animate-in fade-in zoom-in-95 duration-150">
                <div class="px-4 py-2 border-b border-slate-800">
                    <p class="text-xs text-slate-400">Conectado como</p>
                    <p class="text-sm font-semibold text-white truncate">{{ auth()->user()->name ?? 'Usuário' }}</p>
                    <p class="text-xs text-indigo-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>

                <div class="py-1">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                </div>

                <div class="border-t border-slate-800 pt-1">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition-colors text-left">
                            <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Encerrar Sessão
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
