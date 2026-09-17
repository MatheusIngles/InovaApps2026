<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Painel' }} - {{ config('app.name', 'InovaApps 2026') }}</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased selection:bg-indigo-500 selection:text-white">

    <div class="min-h-screen flex flex-col">
        {{-- Menu Lateral (Sidebar) --}}
        @include('components.sidebar')

        {{-- Área de Conteúdo (Deslocada à direita no desktop pelo tamanho da sidebar) --}}
        <div class="flex-1 flex flex-col md:pl-64 transition-all duration-300">
            {{-- Barra Superior (Navbar) --}}
            @include('components.navbar')

            {{-- Conteúdo Principal da Página --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                {{-- Alertas e Notificações --}}
                @include('components.flash-messages')

                {{-- Slot / Seção de Conteúdo --}}
                @yield('content')
            </main>

            {{-- Rodapé Interno --}}
            <footer class="py-4 px-6 border-t border-slate-800/60 text-center text-xs text-slate-500">
                InovaApps 2026 &bull; Ambiente de Desenvolvimento e Avaliação
            </footer>
        </div>
    </div>

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

    {{-- Scripts de Controle de Layout (Sidebar e Dropdown) --}}
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        function toggleUserMenu() {
            const dropdown = document.getElementById('user-menu-dropdown');
            const chevron = document.getElementById('user-menu-chevron');
            const isHidden = dropdown.classList.contains('hidden');

            if (isHidden) {
                dropdown.classList.remove('hidden');
                if (chevron) chevron.classList.add('rotate-180');
            } else {
                dropdown.classList.add('hidden');
                if (chevron) chevron.classList.remove('rotate-180');
            }
        }

        // Fechar dropdowns ao clicar fora
        document.addEventListener('click', function(event) {
            const userMenuContainer = document.getElementById('user-menu-container');
            const dropdown = document.getElementById('user-menu-dropdown');
            const chevron = document.getElementById('user-menu-chevron');

            if (userMenuContainer && !userMenuContainer.contains(event.target)) {
                if (dropdown && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
