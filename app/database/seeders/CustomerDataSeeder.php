<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

class CustomerDataSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/INOVAAPPS_base_de_dados.xlsx');
        $sheets = $this->workbookRows($path);

        DB::transaction(function () use ($sheets): void {
            $situations = collect($sheets['situacao_clientes'])->keyBy('cliente_id');
            $customers = [];

            foreach ($sheets['clientes'] as $row) {
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

            foreach ($sheets['atendimento_mensal'] as $row) {
                $metrics[] = [
                    'customer_id' => $this->customerId($customerIds, $row['cliente_id']),
                    'reference_month' => $this->month($row['mes_ref']),
                    'tickets_opened' => (int) $row['chamados_abertos'],
                    'tickets_critical' => (int) $row['chamados_criticos'],
                    'tickets_reopened' => (int) $row['chamados_reabertos'],
                    'tickets_within_sla' => (int) $row['chamados_dentro_sla'],
                    'sla_percentage' => $row['pct_sla_cumprido'] === '' ? null : $row['pct_sla_cumprido'],
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

            foreach ($sheets['pesquisas_nps'] as $row) {
                $nps[] = [
                    'customer_id' => $this->customerId($customerIds, $row['cliente_id']),
                    'reference_month' => $this->month($row['mes_ref']),
                    'answered' => (int) $row['respondeu'],
                    'score' => $row['nota_nps'] === null || $row['nota_nps'] === '' ? null : (int) $row['nota_nps'],
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
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function workbookRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Não foi possível abrir a planilha: {$path}");
        }

        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value, $row->toArray());

                    if ($headers === null) {
                        $headers = $values;

                        continue;
                    }

                    if (count($headers) === count($values)) {
                        $sheets[$sheet->getName()][] = array_combine($headers, $values);
                    }
                }
            }
        } finally {
            $reader->close();
        }

        foreach (['clientes', 'atendimento_mensal', 'pesquisas_nps', 'situacao_clientes'] as $name) {
            if (! isset($sheets[$name])) {
                throw new RuntimeException("Aba {$name} não encontrada na planilha.");
            }
        }

        return $sheets;
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
