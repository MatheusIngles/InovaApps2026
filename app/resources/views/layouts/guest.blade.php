<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Autenticação' }} - {{ config('app.name', 'InovaApps 2026') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col justify-center bg-slate-950 text-slate-100 antialiased selection:bg-indigo-500 selection:text-white relative overflow-x-hidden">

    {{-- Efeitos de iluminação de fundo (Glows sutis) --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[600px] h-[600px] bg-indigo-600/15 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-10 w-96 h-96 bg-purple-600/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 left-10 w-80 h-80 bg-blue-600/10 rounded-full blur-3xl"></div>
    </div>

    {{-- Conteúdo Principal --}}
    <main class="w-full max-w-md mx-auto px-4 py-8 sm:px-6">
        {{-- Logotipo e Nome do Projeto --}}
        <div class="text-center mb-8">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-3 group">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-500 flex items-center justify-center shadow-lg shadow-indigo-500/25 border border-indigo-400/30 group-hover:scale-105 transition-transform duration-200">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="text-left">
                    <span class="block text-xl font-bold tracking-tight text-white leading-tight">InovaApps</span>
                    <span class="block text-xs font-semibold text-indigo-400 tracking-wider uppercase">Edição 2026</span>
                </div>
            </a>
        </div>

        {{-- Componente de Mensagens Flash --}}
        @include('components.flash-messages')

        {{-- Cartão do Formulário --}}
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/80 rounded-2xl shadow-2xl p-6 sm:p-8 relative">
            @yield('content')
        </div>

        {{-- Rodapé Simples --}}
        <div class="text-center mt-8 text-xs text-slate-500">
            &copy; {{ date('Y') }} InovaApps 2026. Todos os direitos reservados.
        </div>
    </main>

    {{-- Widget VLibras de Acessibilidade --}}
    <div vw class="enabled">
        <div vw-access-button class="active"></div>
        <div vw-plugin-wrapper>
            <div class="vw-plugin-top-wrapper"></div>
        </div>
    </div>
    <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
    <script>
        new window.VLibras.Widget('https://vlibras.gov.br/app');
    </script>

    @stack('scripts')
</body>
</html>
