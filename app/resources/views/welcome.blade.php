<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

    <div class="min-h-screen flex flex-col items-center justify-center px-6">
        <div class="max-w-xl w-full bg-white rounded-2xl shadow-lg p-10 text-center">
            <h1 class="text-3xl font-bold text-red-600 mb-2">Laravel + Tailwind CSS</h1>
            <p class="text-gray-600 mb-6">
                Setup básico funcionando com Tailwind CSS e o plugin
                <span class="font-semibold">VLibras</span> (tradutor de Libras do Governo Federal).
            </p>

            <div class="flex justify-center gap-3">
                <span class="px-4 py-2 rounded-full bg-red-100 text-red-700 text-sm font-medium">Laravel {{ app()->version() }}</span>
                <span class="px-4 py-2 rounded-full bg-blue-100 text-blue-700 text-sm font-medium">Tailwind CSS</span>
                <span class="px-4 py-2 rounded-full bg-green-100 text-green-700 text-sm font-medium">VLibras</span>
            </div>
        </div>
    </div>

    {{-- Widget VLibras --}}
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

</body>
</html>
