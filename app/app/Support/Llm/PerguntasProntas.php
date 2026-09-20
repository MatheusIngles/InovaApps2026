<?php

namespace App\Support\Llm;

/**
 * Perguntas prontas que o chat sugere enquanto a pessoa digita. Todas cabem no que a IA local recebe de contexto
 * (Contexto::carteira e Contexto::empresa) e no que o prompt de sistema explica sobre o cálculo, para ela conseguir responder.
 */
class PerguntasProntas
{
    public const CARTEIRA = [
        'Quem devo ligar primeiro?',
        'Quais são os 5 clientes mais urgentes?',
        'Por que o primeiro cliente da fila está lá?',
        'Resuma a situação da carteira',
        'Quantos clientes ativos temos?',
        'Qual é a receita mensal ativa?',
        'Qual é a exposição mensal total?',
        'Quais clientes da fila estão em nível crítico?',
        'Qual cliente da fila tem o maior contrato?',
        'Qual cliente da fila tem o menor contrato?',
        'Qual é o principal motivo de atenção dos clientes da fila?',
        'Quais métricas pesam mais no cálculo da atenção?',
        'Como a atenção é calculada?',
        'Qual é a diferença entre os níveis Baixo, Médio, Alto e Crítico?',
        'Como a fila de atendimento é ordenada?',
        'A atenção é a probabilidade de o cliente cancelar?',
    ];

    public const CLIENTE = [
        'Por que este cliente está em alerta?',
        'O que devo fazer primeiro com este cliente?',
        'Quais são os principais sinais de alerta deste cliente?',
        'Resuma a situação deste cliente',
        'Como está o NPS deste cliente?',
        'Como está o uso da plataforma nos últimos meses?',
        'Como está o SLA nos últimos meses?',
        'Este cliente tem reclamações formais?',
        'Este cliente tem atraso de pagamento?',
        'Quanto este cliente paga por mês e qual é a exposição?',
        'Quais clientes que cancelaram se parecem com este?',
    ];

    /** @return list<string> */
    public static function para(bool $clienteEmFoco): array
    {
        return $clienteEmFoco ? self::CLIENTE : self::CARTEIRA;
    }
}
