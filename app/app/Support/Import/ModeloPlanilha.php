<?php

namespace App\Support\Import;

use App\Models\Company;
use App\Models\MetricDefinition;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Modelo de planilha em XLSX, no formato da base do desafio: uma aba "Leia-me" que explica tudo, o "dicionario"
 * dos campos e abas de dados ligadas por cliente_id.
 */
class ModeloPlanilha
{
    public const COLUNAS_DICIONARIO = ['aba', 'campo', 'tipo', 'descricao', 'exemplo', 'piora_quando', 'valor_saudavel', 'valor_critico', 'peso'];

    /** @return list<string> */
    private static function leiaMe(): array
    {
        $tipos = implode(', ', array_map(fn (string $t): string => strtok($t, ' ('), MetricDefinition::TYPES));

        return [
            'SEER - Modelo de planilha',
            '',
            'Use esta pasta de trabalho para enviar ao Seer os dados dos seus clientes. Cada aba tem um papel:',
            '',
            'clientes: uma linha por cliente, com porte e valor mensal do contrato (obrigatórios) e segmento e plano (opcionais).',
            'metricas_mensais: uma linha por cliente e por mês (mes_ref no formato AAAA-MM), com uma coluna para cada métrica que você acompanha.',
            'dicionario: descreve cada campo. Preencha e o Seer configura as métricas sozinho no envio.',
            '',
            'Como preencher',
            '1. O cliente_id é a referência que liga as abas. Ele precisa ser igual em todas.',
            '2. Você pode criar quantas abas quiser, cada uma com a coluna cliente_id. Abas com mes_ref são mensais; abas sem mes_ref valem para todos os meses do cliente.',
            '3. Não repita a mesma coluna em duas abas.',
            '4. Deixe a célula vazia quando não houver valor naquele mês. Não apague a linha de cabeçalho.',
            '5. Toda coluna fora de cliente_id, mes_ref, segmento, porte, plano, valor_mensal, situacao, mes_cancelamento e inicio_contrato vira uma métrica da sua empresa. Segmento e plano são opcionais; situacao (Ativo ou Cancelado) e mes_cancelamento (AAAA-MM) indicam quem cancelou.',
            '',
            'Dicionário',
            'Uma linha por campo, com o nome do campo (igual ao cabeçalho da coluna) e o tipo. Tipos aceitos: '.$tipos.'.',
            'Para métricas que entram no cálculo da atenção, preencha também piora_quando (diminui ou aumenta), valor_saudavel, valor_critico e peso (0 a 100).',
            'Campos que você deixar sem dicionário são configurados na tela de envio, com uma sugestão baseada nos dados.',
            '',
            'As linhas de exemplo do dicionário só valem se existir uma coluna com o mesmo nome. Apague ou ajuste como quiser.',
        ];
    }

    /** @return list<list<string|int|float>> */
    private static function dicionario(Company $company): array
    {
        $linhas = [
            ['clientes', 'cliente_id', 'Texto', 'Identificador do cliente. Chave para as demais abas.', 'C007'],
            ['clientes', 'segmento', 'Texto', 'Setor de atuação. Opcional: sem ele, fica "Não informado".', 'Logística'],
            ['clientes', 'porte', 'Texto', 'Tamanho do cliente.', 'Médio'],
            ['clientes', 'plano', 'Texto', 'Plano contratado. Opcional: sem ele, fica "Não informado".', 'Avançado'],
            ['clientes', 'valor_mensal', 'Monetário', 'Receita mensal recorrente do contrato.', 3848],
            ['metricas_mensais', 'mes_ref', 'Texto AAAA-MM', 'Mês de referência. Uma linha por cliente por mês.', '2026-01'],
        ];
        $existentes = $company->metricDefinitions()->orderBy('code')->get();
        foreach ($existentes as $d) {
            $linhas[] = [
                'metricas_mensais', $d->code, self::rotuloTipo($d->value_type), $d->description ?? $d->label, '',
                MetricDefinition::semScore($d->value_type) ? '' : ($d->direction === 'higher' ? 'aumenta' : 'diminui'),
                MetricDefinition::semScore($d->value_type) ? '' : (float) $d->healthy_value,
                MetricDefinition::semScore($d->value_type) ? '' : (float) $d->critical_value,
                MetricDefinition::semScore($d->value_type) ? '' : (float) $d->weight,
            ];
        }
        if ($existentes->isEmpty()) {
            $linhas[] = ['metricas_mensais', 'uso_plataforma_pct', 'Percentual', 'Exemplo: percentual de uso do serviço contratado no mês.', 83, 'diminui', 80, 30, 20];
            $linhas[] = ['metricas_mensais', 'chamados_criticos', 'Contagem', 'Exemplo: chamados com impacto em produção no mês.', 2, 'aumenta', 0, 5, 15];
        }

        return $linhas;
    }

    private static function rotuloTipo(string $tipo): string
    {
        return match ($tipo) {
            'decimal' => 'Numérico', 'integer' => 'Contagem', 'percentage' => 'Percentual', 'currency' => 'Monetário',
            'binary' => 'Binário', 'grade' => 'Nota', 'date' => 'Data', default => 'Texto',
        };
    }

    /** Gera o XLSX em um arquivo temporário e devolve o caminho. */
    public static function gerar(Company $company): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
        $negrito = (new Style)->setFontBold();
        $writer = new Writer;
        $writer->openToFile($caminho);

        $aba = $writer->getCurrentSheet();
        $aba->setName('Leia-me');
        $aba->setColumnWidth(140, 1);
        foreach (self::leiaMe() as $i => $linha) {
            $writer->addRow(Row::fromValues([$linha], $i === 0 || in_array($linha, ['Como preencher', 'Dicionário'], true) ? $negrito : null));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('dicionario');
        $writer->addRow(Row::fromValues(self::COLUNAS_DICIONARIO, $negrito));
        foreach (self::dicionario($company) as $linha) {
            $writer->addRow(Row::fromValues($linha));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('clientes');
        $writer->addRow(Row::fromValues(['cliente_id', 'segmento', 'porte', 'plano', 'valor_mensal'], $negrito));

        $writer->addNewSheetAndMakeItCurrent()->setName('metricas_mensais');
        $writer->addRow(Row::fromValues(['cliente_id', 'mes_ref', ...$company->metricDefinitions()->orderBy('code')->pluck('code')->all()], $negrito));

        $writer->close();

        return $caminho;
    }
}
