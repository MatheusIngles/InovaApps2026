<?php

namespace App\Support\Import;

use App\Models\Company;
use InvalidArgumentException;

class TemplateLayout
{
    public static function headers(Company $company): array
    {
        $codes = $company->metricDefinitions()->orderBy('code')->pluck('code')->all();

        return [...array_keys(ImportService::CAMPOS), ...array_map(fn (string $code): string => 'metrica__'.$code, $codes)];
    }

    public static function customColumns(Company $company): array
    {
        return $company->metricDefinitions()->orderBy('code')->pluck('code')->mapWithKeys(
            fn (string $code): array => [$code => 'metrica__'.$code]
        )->all();
    }

    public static function validarCabecalhos(Company $company, array $headers): void
    {
        $expected = self::headers($company);
        if ($headers !== $expected) {
            $missing = array_values(array_diff($expected, $headers));
            $extra = array_values(array_diff($headers, $expected));
            $detail = [];
            if ($missing) {
                $detail[] = 'Faltam colunas: '.implode(', ', $missing);
            }
            if ($extra) {
                $detail[] = 'Colunas não previstas: '.implode(', ', $extra);
            }
            if (! $detail) {
                $detail[] = 'A ordem das colunas foi alterada.';
            }

            throw new InvalidArgumentException('Planilha fora do padrão. Baixe o modelo atualizado. '.implode(' ', $detail));
        }
    }

    public static function csv(Company $company): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, self::headers($company), ';', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
