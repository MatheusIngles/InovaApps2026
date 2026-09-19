<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Support\Facades\Storage;

/** Aplica o tema da empresa ao painel: cor primária (paleta do Filament), fonte, nome e logo. A cor secundária vira variável CSS (views/tema). */
class Tema
{
    /** Contraste WCAG entre uma cor #rrggbb e o branco (1 a 21). */
    public static function contrasteComBranco(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 1.05 / (0.2126 * $r + 0.7152 * $g + 0.0722 * $b + 0.05);
    }

    /**
     * Cor de destaque do logo: a mais frequente entre os pixels coloridos (ignora fundo transparente, branco, preto e cinzas).
     * Devolve null se o logo não tiver cor (ou se a extensão GD não estiver ativa); o chamador mantém o padrão.
     */
    public static function corDoLogo(string $caminho): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! is_file($caminho)) {
            return null;
        }

        $img = @imagecreatefromstring((string) file_get_contents($caminho));
        if (! $img) {
            return null;
        }
        $img = imagescale($img, 48, 48) ?: $img;
        $grupos = [];

        for ($x = 0; $x < imagesx($img); $x++) {
            for ($y = 0; $y < imagesy($img); $y++) {
                $c = imagecolorat($img, $x, $y);
                if ((($c >> 24) & 0x7F) > 32) { // transparente
                    continue;
                }
                [$r, $g, $b] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                if ($max < 40 || $min > 225 || ($max - $min) < 40) { // preto, branco, cinza
                    continue;
                }
                $chave = ($r >> 5).'.'.($g >> 5).'.'.($b >> 5);
                $grupos[$chave] = ($grupos[$chave] ?? [0, 0, 0, 0]);
                $grupos[$chave] = [$grupos[$chave][0] + 1, $grupos[$chave][1] + $r, $grupos[$chave][2] + $g, $grupos[$chave][3] + $b];
            }
        }

        if (! $grupos) {
            return null;
        }
        [$n, $r, $g, $b] = collect($grupos)->sortByDesc(0)->first();
        $hex = sprintf('#%02x%02x%02x', $r / $n, $g / $n, $b / $n);

        return self::escurecerAte($hex, 3);
    }

    /** Escurece a cor até ter o contraste mínimo com o branco (botões primários levam texto branco). */
    public static function escurecerAte(string $hex, float $contraste): string
    {
        while (self::contrasteComBranco($hex) < $contraste) {
            $hex = self::escurecer($hex, .9);
        }

        return $hex;
    }

    public static function escurecer(string $hex, float $fator = .8): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', $r * $fator, $g * $fator, $b * $fator);
    }

    /** Cores sugeridas pelo logo: [primária, secundária], ou null para manter as padrão. */
    public static function coresDoLogo(string $caminho): ?array
    {
        $cor = self::corDoLogo($caminho);

        return $cor ? [$cor, self::escurecer($cor)] : null;
    }

    public static function aplicar(Company $company): void
    {
        $t = $company->tema();
        $painel = Filament::getCurrentOrDefaultPanel();

        FilamentColor::register(['primary' => Color::hex($t['primary'])]);
        $painel->brandName($t['brand'])->font($t['font']);

        if ($t['logo']) {
            $painel->brandLogo(Storage::disk('public')->url($t['logo']))->brandLogoHeight('2rem');
        }
    }
}
