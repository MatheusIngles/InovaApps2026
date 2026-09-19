<?php

namespace App\Support\Import;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Lê CSV/XLSX e devolve uma tabela plana (cabeçalhos + linhas associativas).
 * A pasta de trabalho do desafio (4 abas ligadas por cliente_id/mes_ref) é achatada automaticamente.
 */
class PlanilhaReader
{
    private const ABAS_DESAFIO = ['clientes', 'atendimento_mensal', 'pesquisas_nps', 'situacao_clientes'];

    /** @return array{cabecalhos: list<string>, linhas: list<array<string, mixed>>} */
    public static function ler(string $caminho, string $extensao): array
    {
        if (! is_file($caminho)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        $abas = match (strtolower($extensao)) {
            'xlsx' => self::abasXlsx($caminho),
            'csv', 'txt' => ['csv' => self::linhasCsv($caminho)],
            default => throw new RuntimeException('Formato não suportado: envie um arquivo .xlsx ou .csv.'),
        };

        if (count(array_intersect_key($abas, array_flip(self::ABAS_DESAFIO))) === 4) {
            return self::achatar($abas);
        }

        $linhas = reset($abas) ?: [];
        $cabecalhos = array_values(array_filter(array_map(fn ($h) => trim((string) $h), $linhas[0] ?? []), fn ($h) => $h !== ''));

        if (! $cabecalhos) {
            throw new RuntimeException('A planilha está vazia ou sem linha de cabeçalho.');
        }

        return ['cabecalhos' => $cabecalhos, 'linhas' => self::associar($linhas, $cabecalhos)];
    }

    /** @return array<string, list<list<mixed>>> */
    private static function abasXlsx(string $caminho): array
    {
        $reader = new XlsxReader;
        $reader->open($caminho);
        $abas = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $abas[$sheet->getName()][] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $row->toArray());
                }
            }
        } finally {
            $reader->close();
        }

        return $abas;
    }

    /** @return list<list<mixed>> */
    private static function linhasCsv(string $caminho): array
    {
        $bruto = (string) file_get_contents($caminho, false, null, 0, 65536);
        $primeira = strtok($bruto, "\n") ?: '';
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = substr_count($primeira, ';') > substr_count($primeira, ',') ? ';' : ',';
        $options->ENCODING = mb_check_encoding($bruto, 'UTF-8') ? 'UTF-8' : 'ISO-8859-1'; // CSV exportado pelo Excel

        $reader = new CsvReader($options);
        $reader->open($caminho);
        $linhas = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $linhas[] = array_map(fn ($v) => is_string($v) ? trim(ltrim($v, "\xEF\xBB\xBF")) : $v, $row->toArray());
                }
            }
        } finally {
            $reader->close();
        }

        return $linhas;
    }

    /** @return list<array<string, mixed>> */
    private static function associar(array $linhas, array $cabecalhos): array
    {
        $out = [];

        foreach (array_slice($linhas, 1) as $linha) {
            $linha = array_pad(array_slice($linha, 0, count($cabecalhos)), count($cabecalhos), null);

            if (array_filter($linha, fn ($v) => $v !== null && $v !== '')) { // ignora linhas totalmente vazias
                $out[] = array_combine($cabecalhos, $linha);
            }
        }

        return $out;
    }

    /** Junta as 4 abas do desafio em linhas cliente × mês com os nomes canônicos de coluna. */
    private static function achatar(array $abas): array
    {
        $tabela = fn (string $nome) => self::associar($abas[$nome], array_map('strval', $abas[$nome][0]));
        $situacao = array_column($tabela('situacao_clientes'), null, 'cliente_id');
        $pesquisas = [];
        $atendimentos = [];

        foreach ($tabela('pesquisas_nps') as $p) {
            $pesquisas[$p['cliente_id']][$p['mes_ref']] = $p;
        }
        foreach ($tabela('atendimento_mensal') as $a) {
            $atendimentos[$a['cliente_id']][$a['mes_ref']] = $a;
        }

        $linhas = [];

        foreach ($tabela('clientes') as $c) {
            $id = $c['cliente_id'];
            $base = $c + ['situacao' => $situacao[$id]['situacao'] ?? 'Ativo', 'mes_cancelamento' => $situacao[$id]['mes_cancelamento'] ?? null];
            // meses com atendimento e/ou pesquisa; cliente sem nenhum mês vira uma linha só de cadastro
            $meses = ($atendimentos[$id] ?? []) + array_map(fn ($p) => ['mes_ref' => $p['mes_ref']], $pesquisas[$id] ?? []);

            foreach ($meses ?: [[]] as $mes => $atendimento) {
                $p = $pesquisas[$id][$mes] ?? [];
                $linhas[] = $base + $atendimento + ['respondeu' => $p['respondeu'] ?? null, 'nota_nps' => $p['nota_nps'] ?? null, 'classificacao_nps' => $p['classificacao_nps'] ?? null];
            }
        }

        $cabecalhos = array_keys(array_reduce($linhas, fn ($c, $l) => $c + $l, []));

        return ['cabecalhos' => $cabecalhos, 'linhas' => $linhas];
    }
}
