{{-- Tema do painel (claro e escuro): superfícies em camadas, azul para ações e destaques; cor secundária e fonte vêm da empresa. --}}
@php
    $empresa = app(\App\Support\Tenancy\CompanyContext::class)->current() ?? \App\Support\Tenancy\TenantResolver::company(request());
    $secundaria = $empresa?->tema()['secondary'] ?? \App\Models\Company::TEMA_PADRAO['secondary'];
    [$r, $g, $b] = sscanf($secundaria, '#%02x%02x%02x');
    $textoSobre = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > 150 ? '#0f172a' : '#ffffff'; // texto legível sobre qualquer cor
@endphp
<style>
    :root {
        --tenant-secondary: {{ $secundaria }};
        --on-secondary: {{ $textoSobre }};
    }
</style>
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v={{ filemtime(public_path('css/panel.css')) }}">
