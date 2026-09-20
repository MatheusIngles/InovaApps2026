<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/** Avatar com as iniciais em azul, gerado localmente (sem enviar o nome do usuário a serviços externos). */
class AvatarIniciais implements AvatarProvider
{
    public function get(Model $record): string
    {
        $iniciais = collect(preg_split('/\s+/', trim((string) $record->name)))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('') ?: '?';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#1e40af"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="26" font-weight="700" fill="#fff">'.e($iniciais).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
