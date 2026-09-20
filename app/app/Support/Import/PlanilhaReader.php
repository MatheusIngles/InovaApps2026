<?php

namespace App\Support\Import;

use Illuminate\Support\Str;
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

    /** Abas de apoio do modelo: não trazem dados de clientes. */
    private const ABAS_DE_APOIO = ['leiame', 'dicionario', 'instrucoes'];

    /**
     * Lê o modelo livre. No XLSX pode haver várias abas de dados, desde que cada uma tenha `cliente_id`: as abas com
     * `mes_ref` (mensais) definem as linhas cliente × mês e as demais (cadastro) valem para todos os meses do cliente.
     * Abas "Leia-me" e "dicionário" não são dados; o dicionário volta em `dicionario` para preencher as métricas.
     *
     * @return array{cabecalhos: list<string>, linhas: list<array<string, mixed>>, dicionario: array<string, array<string, string>>}
     */
    public static function lerModelo(string $caminho, string $extensao, ?int $previa = null): array
    {
        if (! is_file($caminho)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        $max = $previa === null ? null : $previa + 1;
        $abas = match (strtolower($extensao)) {
            'xlsx' => self::abasXlsx($caminho, $max),
            'csv' => ['csv' => self::linhasCsv($caminho, $max)],
            default => throw new RuntimeException('Formato não suportado: use o modelo .csv ou uma planilha .xlsx.'),
        };
        $dadosDe = fn (array $todas): array => array_filter($todas, fn (string $nome): bool => ! in_array(self::chave($nome), self::ABAS_DE_APOIO, true), ARRAY_FILTER_USE_KEY);
        $dados = $dadosDe($abas);
        if (count($dados) > 1 && $max !== null) {
            $abas = self::abasXlsx($caminho, null); // a junção por cliente precisa das abas inteiras
            $dados = $dadosDe($abas);
        }
        if (! $dados) {
            throw new RuntimeException('Planilha fora do padrão: não há nenhuma aba de dados.');
        }

        $tabela = count($dados) === 1
            ? self::tabela(reset($dados))
            : self::mesclar(array_map(fn (array $linhas): array => self::tabela($linhas), $dados));

        return $tabela + ['dicionario' => self::dicionario($abas)];
    }

    /** @return array{cabecalhos: list<string>, linhas: list<array<string, mixed>>} */
    private static function tabela(array $linhas): array
    {
        $cabecalhos = array_map(fn ($value): string => trim((string) $value), $linhas[0] ?? []);
        if (! $cabecalhos || in_array('', $cabecalhos, true) || count($cabecalhos) !== count(array_unique($cabecalhos))) {
            throw new RuntimeException('Planilha fora do padrão: cabeçalhos vazios ou duplicados.');
        }
        foreach (array_slice($linhas, 1) as $row) {
            if (array_filter(array_slice($row, count($cabecalhos)), fn ($v) => $v !== null && $v !== '')) {
                throw new RuntimeException('Planilha fora do padrão: uma linha contém mais colunas que o cabeçalho.');
            }
        }

        return ['cabecalhos' => $cabecalhos, 'linhas' => self::associar($linhas, $cabecalhos)];
    }

    /**
     * Junta abas por cliente_id (e mes_ref, nas mensais). Colunas iguais em abas diferentes são ambíguas e recusadas.
     *
     * @param  array<string, array{cabecalhos: list<string>, linhas: list<array<string, mixed>>}>  $tabelas
     * @return array{cabecalhos: list<string>, linhas: list<array<string, mixed>>}
     */
    private static function mesclar(array $tabelas): array
    {
        $chaves = ['cliente_id', 'mes_ref'];
        $dono = [];
        $mensais = [];
        $cadastros = [];
        foreach ($tabelas as $nome => $tabela) {
            if (! in_array('cliente_id', $tabela['cabecalhos'], true)) {
                throw new RuntimeException("A aba \"{$nome}\" não tem a coluna cliente_id; ela é a referência para ligar as abas.");
            }
            foreach (array_diff($tabela['cabecalhos'], $chaves) as $coluna) {
                if (isset($dono[$coluna])) {
                    throw new RuntimeException("A coluna \"{$coluna}\" aparece nas abas \"{$dono[$coluna]}\" e \"{$nome}\". Use cada coluna em uma aba só.");
                }
                $dono[$coluna] = $nome;
            }
            if (in_array('mes_ref', $tabela['cabecalhos'], true)) {
                $mensais[$nome] = $tabela;
            } else {
                $cadastros[$nome] = $tabela;
            }
        }
        if (! $mensais) {
            throw new RuntimeException('Nenhuma aba tem a coluna mes_ref: preciso de pelo menos uma aba com uma linha por cliente e mês.');
        }

        $porCliente = [];
        foreach ($cadastros as $tabela) {
            foreach ($tabela['linhas'] as $linha) {
                $id = trim((string) $linha['cliente_id']);
                unset($linha['cliente_id']);
                $porCliente[$id] = ($porCliente[$id] ?? []) + $linha;
            }
        }
        $linhas = [];
        foreach ($mensais as $tabela) {
            foreach ($tabela['linhas'] as $linha) {
                $id = trim((string) $linha['cliente_id']);
                $mes = trim((string) $linha['mes_ref']);
                $chave = $id.'|'.(preg_match('/^\d{4}-\d{2}/', $mes) ? substr($mes, 0, 7) : $mes);
                $linhas[$chave] = ($linhas[$chave] ?? ['cliente_id' => $id, 'mes_ref' => $mes]) + $linha;
            }
        }
        $cabecalhos = [...$chaves, ...array_keys($dono)];
        $completas = [];
        foreach ($linhas as $linha) {
            $linha += $porCliente[$linha['cliente_id']] ?? [];
            $completas[] = array_replace(array_fill_keys($cabecalhos, null), $linha);
        }

        return ['cabecalhos' => $cabecalhos, 'linhas' => $completas];
    }

    /**
     * Dicionário preenchido pela pessoa: campo => [tipo, descricao, piora_quando, valor_saudavel, valor_critico, peso].
     *
     * @return array<string, array<string, string>>
     */
    private static function dicionario(array $abas): array
    {
        foreach ($abas as $nome => $linhas) {
            if (self::chave($nome) !== 'dicionario' || ! $linhas) {
                continue;
            }
            $cabecalhos = array_map(fn ($h): string => self::chave((string) $h), $linhas[0]);
            $dicionario = [];
            foreach (array_slice($linhas, 1) as $linha) {
                $linha = array_slice(array_pad($linha, count($cabecalhos), null), 0, count($cabecalhos));
                $registro = array_map(fn ($v): string => trim((string) $v), array_combine($cabecalhos, $linha));
                if (($registro['campo'] ?? '') !== '') {
                    $dicionario[$registro['campo']] = $registro;
                }
            }

            return $dicionario;
        }

        return [];
    }

    /** Nome sem acento, caixa ou separadores: "Leia-me" e "Dicionário" viram "leiame" e "dicionario". */
    private static function chave(string $nome): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($nome)));
    }

    /** @return array{cabecalhos: list<string>, linhas: list<array<string, mixed>>} */
    public static function ler(string $caminho, string $extensao, ?int $previa = null): array
    {
        if (! is_file($caminho)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        // $previa: lê só o cabeçalho e as primeiras linhas (não carrega planilhas enormes só para mostrar a prévia)
        $max = $previa === null ? null : $previa + 1;
        $abas = match (strtolower($extensao)) {
            'xlsx' => self::abasXlsx($caminho, $max),
            'csv', 'txt' => ['csv' => self::linhasCsv($caminho, $max)],
            default => throw new RuntimeException('Formato não suportado: envie um arquivo .xlsx ou .csv.'),
        };

        if (count(array_intersect_key($abas, array_flip(self::ABAS_DESAFIO))) === 4) {
            // as 4 abas se ligam por cliente: o achatamento precisa delas inteiras (planilha pequena)
            return self::achatar($max === null ? $abas : self::abasXlsx($caminho, null));
        }

        $linhas = reset($abas) ?: [];
        $cabecalhos = array_values(array_filter(array_map(fn ($h) => trim((string) $h), $linhas[0] ?? []), fn ($h) => $h !== ''));

        if (! $cabecalhos) {
            throw new RuntimeException('A planilha está vazia ou sem linha de cabeçalho.');
        }

        return ['cabecalhos' => $cabecalhos, 'linhas' => self::associar($linhas, $cabecalhos)];
    }

    /** @return array<string, list<list<mixed>>> */
    private static function abasXlsx(string $caminho, ?int $max = null): array
    {
        $reader = new XlsxReader;
        $reader->open($caminho);
        $abas = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $abas[$sheet->getName()] = [];
                foreach ($sheet->getRowIterator() as $i => $row) {
                    if ($max !== null && $i > $max) {
                        break;
                    }
                    $abas[$sheet->getName()][] = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $row->toArray());
                }
            }
        } finally {
            $reader->close();
        }

        return $abas;
    }

    /** @return list<list<mixed>> */
    private static function linhasCsv(string $caminho, ?int $max = null): array
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
                foreach ($sheet->getRowIterator() as $i => $row) {
                    if ($max !== null && $i > $max) {
                        break;
                    }
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
