<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class CustomerDataSeeder extends Seeder
{
    public function run(): void
    {
        $path = dirname(database_path(), 2).DIRECTORY_SEPARATOR.'INOVAAPPS_base_de_dados.xlsx';
        $workbook = new ZipArchive;

        if ($workbook->open($path) !== true) {
            throw new RuntimeException("Não foi possível abrir a planilha: {$path}");
        }

        try {
            DB::transaction(function () use ($workbook): void {
                $situations = collect($this->rows($workbook, 6))->keyBy('cliente_id');
                $customers = [];

                foreach ($this->rows($workbook, 3) as $row) {
                    $situation = $situations->get($row['cliente_id']);

                    if ($situation === null) {
                        throw new RuntimeException("Situação ausente para {$row['cliente_id']}");
                    }

                    $customers[] = [
                        'external_code' => $row['cliente_id'],
                        'segment' => $row['segmento'],
                        'size' => $row['porte'],
                        'plan' => $row['plano'],
                        'monthly_value' => $row['valor_mensal'],
                        'contracted_sla_hours' => (int) $row['sla_contratado_h'],
                        'contract_started_at' => $row['inicio_contrato'],
                        'status' => $situation['situacao'],
                        'cancelled_at' => $this->month($situation['mes_cancelamento']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('customers')->upsert($customers, ['external_code'], [
                    'segment', 'size', 'plan', 'monthly_value', 'contracted_sla_hours',
                    'contract_started_at', 'status', 'cancelled_at', 'updated_at',
                ]);

                $customerIds = DB::table('customers')->pluck('id', 'external_code');
                $metrics = [];

                foreach ($this->rows($workbook, 4) as $row) {
                    $metrics[] = [
                        'customer_id' => $this->customerId($customerIds, $row['cliente_id']),
                        'reference_month' => $this->month($row['mes_ref']),
                        'tickets_opened' => (int) $row['chamados_abertos'],
                        'tickets_critical' => (int) $row['chamados_criticos'],
                        'tickets_reopened' => (int) $row['chamados_reabertos'],
                        'tickets_within_sla' => (int) $row['chamados_dentro_sla'],
                        'sla_percentage' => $row['pct_sla_cumprido'],
                        'avg_resolution_hours' => $row['tempo_medio_resolucao_h'],
                        'formal_complaints' => (int) $row['reclamacoes_formais'],
                        'platform_usage_percentage' => $row['uso_plataforma_pct'],
                        'payment_delay_days' => (int) $row['dias_atraso_pagamento'],
                        'meetings_expected' => (int) $row['reunioes_previstas'],
                        'meetings_completed' => (int) $row['reunioes_realizadas'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($metrics, 250) as $chunk) {
                    DB::table('customer_metrics')->upsert($chunk, ['customer_id', 'reference_month'], [
                        'tickets_opened', 'tickets_critical', 'tickets_reopened', 'tickets_within_sla',
                        'sla_percentage', 'avg_resolution_hours', 'formal_complaints',
                        'platform_usage_percentage', 'payment_delay_days', 'meetings_expected',
                        'meetings_completed', 'updated_at',
                    ]);
                }

                $nps = [];

                foreach ($this->rows($workbook, 5) as $row) {
                    $nps[] = [
                        'customer_id' => $this->customerId($customerIds, $row['cliente_id']),
                        'reference_month' => $this->month($row['mes_ref']),
                        'answered' => (int) $row['respondeu'],
                        'score' => $row['nota_nps'] === '' ? null : (int) $row['nota_nps'],
                        'classification' => $row['classificacao_nps'] ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($nps, 250) as $chunk) {
                    DB::table('customer_nps')->upsert($chunk, ['customer_id', 'reference_month'], [
                        'answered', 'score', 'classification', 'updated_at',
                    ]);
                }
            });
        } finally {
            $workbook->close();
        }
    }

    /** @return list<array<string, string>> */
    private function rows(ZipArchive $workbook, int $sheet): array
    {
        $xml = $workbook->getFromName("xl/worksheets/sheet{$sheet}.xml");

        if ($xml === false) {
            throw new RuntimeException("Aba {$sheet} não encontrada na planilha.");
        }

        $worksheet = new SimpleXMLElement($xml);
        $headers = [];
        $rows = [];

        foreach ($worksheet->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $column = preg_replace('/\d+/', '', (string) $cell['r']);
                $values[$column] = (string) ($cell->is->t ?? $cell->v);
            }

            if ($headers === []) {
                $headers = $values;

                continue;
            }

            $record = [];

            foreach ($headers as $column => $name) {
                $record[$name] = $values[$column] ?? '';
            }

            $rows[] = $record;
        }

        return $rows;
    }

    private function month(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return strlen($value) === 7 ? $value.'-01' : $value;
    }

    private function customerId(Collection $customerIds, string $code): int
    {
        $id = $customerIds->get($code);

        if ($id === null) {
            throw new RuntimeException("Cliente não encontrado: {$code}");
        }

        return (int) $id;
    }
}
