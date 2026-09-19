<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Support\Risco;
use Illuminate\Database\Seeder;
use OpenSpout\Reader\XLSX\Reader;

/** Importa a base do desafio (database/data/INOVAAPPS_base_de_dados.xlsx) e calcula o risco de cada cliente. */
class CarteiraSeeder extends Seeder
{
    private const NOMES = ['Alfa', 'Boreal', 'Cerrado', 'Delta', 'Estrela', 'Faro', 'Guará', 'Horizonte', 'Ipê', 'Jequitibá',
        'Kappa', 'Litoral', 'Mercúrio', 'Nordeste', 'Órion', 'Pampa', 'Quasar', 'Ribeira', 'Sul', 'Tupã'];

    private const SEGMENTOS = ['Logistica' => 'Logística', 'Saude' => 'Saúde', 'Educacao' => 'Educação', 'Servicos' => 'Serviços', 'Industria' => 'Indústria'];

    public function run(): void
    {
        $aba = $this->abas(database_path('data/INOVAAPPS_base_de_dados.xlsx'));
        $situacao = array_column($aba['situacao_clientes'], null, 'cliente_id');
        $porCliente = fn (string $nome) => collect($aba[$nome])->groupBy('cliente_id')->map(fn ($g) => $g->sortBy('mes_ref')->values()->all());
        $hist = $porCliente('atendimento_mensal');
        $nps = $porCliente('pesquisas_nps');

        $linhas = [];
        foreach ($aba['clientes'] as $c) {
            $id = $c['cliente_id'];
            $r = Risco::calcular($hist[$id] ?? [], $nps[$id] ?? []);
            $segmento = self::SEGMENTOS[$c['segmento']] ?? $c['segmento'];
            $linhas[$id] = [
                'row' => [
                    'nome' => "$segmento ".self::NOMES[hexdec(substr(md5($id), -6)) % 20].' '.substr($id, 1),
                    'segmento' => $segmento, 'porte' => $c['porte'], 'plano' => $c['plano'],
                    'valor' => (int) $c['valor_mensal'], 'sla_h' => (int) $c['sla_contratado_h'], 'inicio' => substr($c['inicio_contrato'], 0, 10),
                    'status' => $situacao[$id]['situacao'], 'mes_cancel' => $situacao[$id]['mes_cancelamento'] ?: null,
                    'score' => $r['score'], 'nivel' => $r['nivel'], 'exposicao' => (int) round($r['score'] / 100 * $c['valor_mensal']),
                    'sinais' => $r['sinais'],
                    'hist' => array_map(fn ($h) => [
                        'mes' => $h['mes_ref'], 'abertos' => (int) $h['chamados_abertos'], 'criticos' => (int) $h['chamados_criticos'],
                        'reabertos' => (int) $h['chamados_reabertos'], 'sla' => (float) ($h['pct_sla_cumprido'] ?? 100), 'uso' => (float) $h['uso_plataforma_pct'],
                        'recl' => (int) $h['reclamacoes_formais'], 'atraso' => (int) $h['dias_atraso_pagamento'],
                        'reunioes' => $h['reunioes_realizadas'].'/'.$h['reunioes_previstas'],
                    ], $hist[$id] ?? []),
                    'nps' => array_map(fn ($n) => ['mes' => $n['mes_ref'], 'nota' => $n['nota_nps'] === null || $n['nota_nps'] === '' ? '—' : (string) (int) $n['nota_nps']], $nps[$id] ?? []),
                ],
                'sev' => $r['sev'],
            ];
        }

        $cancelados = array_filter($linhas, fn ($l) => $l['row']['status'] === 'Cancelado');
        foreach ($linhas as $id => $l) {
            $sim = [];
            foreach ($cancelados as $outro => $o) {
                if ($outro !== $id) {
                    $sim[] = ['codigo' => $outro, 'nome' => $o['row']['nome'], 'mes_cancel' => $o['row']['mes_cancel'], 'sim' => Risco::semelhanca($l['sev'], $o['sev'])];
                }
            }
            usort($sim, fn ($a, $b) => $b['sim'] <=> $a['sim']);
            Empresa::updateOrCreate(['codigo' => $id], $l['row'] + ['similares' => array_slice($sim, 0, 3)]);
        }
    }

    /** @return array<string, array<int, array<string, mixed>>> abas → linhas associativas pelo cabeçalho */
    private function abas(string $arquivo): array
    {
        $reader = new Reader;
        $reader->open($arquivo);
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $cab = null;
            foreach ($sheet->getRowIterator() as $row) {
                $v = array_map(fn ($x) => $x instanceof \DateTimeInterface ? $x->format('Y-m-d') : $x, $row->toArray());
                if ($cab === null) {
                    $cab = $v;
                } elseif (count($cab) === count($v)) {
                    $out[$sheet->getName()][] = array_combine($cab, $v);
                }
            }
        }
        $reader->close();

        return $out;
    }
}
