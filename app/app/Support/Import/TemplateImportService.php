<?php

namespace App\Support\Import;

use App\Models\Company;
use DateTimeImmutable;
use InvalidArgumentException;

class TemplateImportService
{
    /** @param array{cabecalhos: array, linhas: array} $table */
    public static function importar(Company $company, array $table): array
    {
        self::validar($company, $table);
        $fields = array_keys(ImportService::CAMPOS);

        return ImportService::importar(
            $company,
            $table['linhas'],
            array_combine($fields, $fields),
            TemplateLayout::customColumns($company),
        ) + ['valores_metricas' => 0];
    }

    /** @param array{cabecalhos: array, linhas: array} $table */
    public static function validar(Company $company, array $table): void
    {
        TemplateLayout::validarCabecalhos($company, $table['cabecalhos']);
        if (! $table['linhas']) {
            throw new InvalidArgumentException('Planilha fora do padrão: adicione pelo menos uma linha ao modelo.');
        }

        $integerFields = ['chamados_abertos', 'chamados_criticos', 'chamados_reabertos', 'chamados_dentro_sla', 'reclamacoes_formais', 'dias_atraso_pagamento', 'reunioes_previstas', 'reunioes_realizadas'];
        $decimalFields = ['pct_sla_cumprido', 'tempo_medio_resolucao_h', 'uso_plataforma_pct'];
        $customColumns = TemplateLayout::customColumns($company);
        $seen = [];
        $profiles = [];

        foreach ($table['linhas'] as $index => $row) {
            $line = $index + 2;
            foreach (['cliente_id', 'mes_ref', 'segmento', 'porte', 'plano', 'valor_mensal', 'sla_contratado_h', 'inicio_contrato', 'situacao'] as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') {
                    throw new InvalidArgumentException("Linha {$line}: {$field} é obrigatório no modelo.");
                }
            }
            foreach (['cliente_id', 'segmento', 'porte', 'plano', 'classificacao_nps'] as $field) {
                if (mb_strlen(trim((string) ($row[$field] ?? ''))) > 255) {
                    throw new InvalidArgumentException("Linha {$line}: {$field} excede 255 caracteres.");
                }
            }
            $month = self::month(trim((string) $row['mes_ref']));
            $started = trim((string) $row['inicio_contrato']);
            if ($month === null || ! self::validDate($started, 'Y-m-d')) {
                throw new InvalidArgumentException("Linha {$line}: use mes_ref em AAAA-MM e inicio_contrato em AAAA-MM-DD.");
            }
            $status = trim((string) $row['situacao']);
            $cancelledRaw = trim((string) ($row['mes_cancelamento'] ?? ''));
            $cancelled = self::month($cancelledRaw);
            if (! in_array($status, ['Ativo', 'Cancelado'], true)
                || ($status === 'Cancelado' && ($cancelled === null || $month >= $cancelled))
                || ($status === 'Ativo' && $cancelledRaw !== '')) {
                throw new InvalidArgumentException("Linha {$line}: situação ou mês de cancelamento inválido.");
            }
            $key = trim((string) $row['cliente_id']).':'.$month;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Linha {$line}: cliente e mês duplicados no arquivo.");
            }
            $seen[$key] = true;
            $code = trim((string) $row['cliente_id']);
            $profile = array_map(
                fn (string $field): string => trim((string) ($row[$field] ?? '')),
                ['segmento', 'porte', 'plano', 'valor_mensal', 'sla_contratado_h', 'inicio_contrato', 'situacao', 'mes_cancelamento'],
            );
            if (isset($profiles[$code]) && $profiles[$code] !== $profile) {
                throw new InvalidArgumentException("Linha {$line}: dados do contrato divergentes para o mesmo cliente.");
            }
            $profiles[$code] = $profile;

            if (self::numeric($row['valor_mensal'], 'valor_mensal', $line, 2) > 9999999999.99
                || self::numeric($row['sla_contratado_h'], 'sla_contratado_h', $line, 0) > 65535) {
                throw new InvalidArgumentException("Linha {$line}: valor mensal ou SLA contratado fora da faixa do modelo.");
            }
            foreach ($integerFields as $field) {
                $value = self::numeric($row[$field] ?? null, $field, $line, 0, optional: true);
                if ($value !== null && $value > 65535) {
                    throw new InvalidArgumentException("Linha {$line}: {$field} excede o limite de 65535.");
                }
            }
            foreach ($decimalFields as $field) {
                $value = self::numeric($row[$field] ?? null, $field, $line, 2, optional: true);
                if ($value !== null && in_array($field, ['pct_sla_cumprido', 'uso_plataforma_pct'], true) && $value > 100) {
                    throw new InvalidArgumentException("Linha {$line}: {$field} deve estar entre 0 e 100.");
                }
                if ($value !== null && $field === 'tempo_medio_resolucao_h' && $value > 999999.99) {
                    throw new InvalidArgumentException("Linha {$line}: tempo_medio_resolucao_h fora da faixa do modelo.");
                }
            }
            $answered = trim((string) ($row['respondeu'] ?? ''));
            $nps = trim((string) ($row['nota_nps'] ?? ''));
            $classification = trim((string) ($row['classificacao_nps'] ?? ''));
            if (! in_array($answered, ['', '0', '1'], true)
                || ($answered === '1' && ($nps === '' || ! ctype_digit($nps) || (int) $nps > 10))
                || ($answered !== '1' && $nps !== '')
                || ($answered === '' && $classification !== '')
                || ($answered === '0' && ! in_array($classification, ['', 'Sem resposta'], true))
                || ($answered === '1' && $classification !== '' && $classification !== match (true) {
                    (int) $nps <= 6 => 'Detrator',
                    (int) $nps <= 8 => 'Neutro',
                    default => 'Promotor',
                })) {
                throw new InvalidArgumentException("Linha {$line}: responda 1 com nota de 0 a 10, 0 sem nota, ou deixe ambos vazios.");
            }
            foreach ($customColumns as $column) {
                self::numeric($row[$column] ?? null, $column, $line, 4, optional: true, allowNegative: true);
            }
        }
    }

    private static function validDate(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private static function month(string $value): ?string
    {
        if (self::validDate($value, 'Y-m')) {
            return $value;
        }

        return self::validDate($value, 'Y-m-d') && str_ends_with($value, '-01') ? substr($value, 0, 7) : null;
    }

    private static function numeric(mixed $raw, string $field, int $line, int $precision, bool $optional = false, bool $allowNegative = false): ?float
    {
        $value = trim((string) $raw);
        if ($optional && $value === '') {
            return null;
        }
        $pattern = $allowNegative ? '-?' : '';
        $fraction = $precision > 0 ? '(?:[.,]\\d{1,'.$precision.'})?' : '';
        if (! preg_match('/^'.$pattern.'\\d+'.$fraction.'$/', $value)) {
            throw new InvalidArgumentException("Linha {$line}: {$field} deve ser numérico".($optional ? ' ou vazio.' : '.'));
        }

        $number = (float) str_replace(',', '.', $value);
        if (abs($number) > 9999999999.9999) {
            throw new InvalidArgumentException("Linha {$line}: {$field} excede a faixa numérica do modelo.");
        }

        return $number;
    }
}
