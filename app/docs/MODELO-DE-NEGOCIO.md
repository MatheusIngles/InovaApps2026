# Modelo de negócio: Seer

Resposta à pergunta do desafio: "qual seria o modelo de negócio de uma solução como essa?". Os números de contexto vêm da base entregue; preços e percentuais são **hipóteses de trabalho** a validar.

## Problema e valor
- Contrato recorrente: cada saída perde uma anuidade inteira e ainda custa a aquisição de um substituto.
- Na carteira analisada, 22 de 80 clientes saíram em 18 meses: **R$ 275 mil de receita mensal (R$ 3,3 milhões por ano)**.
- Ninguém lê atendimento, SLA, uso, pagamento e NPS em conjunto, e a equipe age depois que o cliente avisa.
- O Seer entrega uma **fila de atendimento** (com quem falar, por que e em que ordem), priorizada por risco × valor do contrato, com a evidência de cada alerta.

## Quem paga
Empresas de serviço com contrato recorrente e uma equipe de relacionamento pequena (as mesmas do caso: dezenas a poucas centenas de contas). Quem decide: diretoria comercial ou de sucesso do cliente. Quem usa: gestores de conta.

## O que se vende
Assinatura por empresa, em camadas, com implantação rápida (importação da planilha, mapeamento de colunas e pesos calibrados nos cancelamentos da própria carteira).

| Camada | Para quem | Inclui |
|---|---|---|
| Essencial | até ~100 contas | fila priorizada, evidências por cliente, importação mensal, relatório em PDF |
| Profissional | até ~500 contas | tudo acima + assistente com IA local, alertas de mudança de nível, calibração dos pesos e evidências (antecedência e alarme falso) |
| Enterprise | acima de 500 contas ou várias unidades | multiempresa, tema próprio, integração direta com os sistemas de chamados/SLA/NPS, IA avançada por API |

Preço: **valor fixo mensal por faixa de contas monitoradas** (hipótese: R$ 0,50 a R$ 2 por conta ativa por mês em contas de ticket médio de R$ 12 mil; ajustar ao ticket do cliente). Uma alternativa é uma parte pequena da receita preservada, com meta acordada.

## Por que compensa (conta simples)
Com a carteira do desafio: se o Seer evitar **10% dos cancelamentos** (2 dos 22), preserva cerca de **R$ 27 mil por mês, R$ 330 mil por ano**. Mesmo um preço de algumas dezenas de milhares por ano se paga em poucos meses.

## Custos e margem
- Infraestrutura pequena: aplicação web + banco; o processamento do score é leve.
- IA: o modelo **local (Ollama)** cobre o uso comum sem custo por chamada; a API externa só entra em perguntas complexas (custo variável controlado).
- Diferencial de custo de aquisição: implantação por planilha, sem projeto de integração no início.

## Como cresce
1. Entrada pelo desafio: prova com a carteira real (antecedência do alerta e alarme falso mostrados na tela de Evidências).
2. Expansão: mais contas e mais unidades da mesma empresa; integração automática dos dados (fim da planilha).
3. Aprendizado: cada cancelamento novo recalibra os pesos da própria carteira, o que aumenta a precisão com o uso.

## Métricas de sucesso
Receita retida atribuída a alertas, antecedência mediana do alerta, taxa de alarme falso (para a equipe não deixar de olhar), tempo até a primeira ação após um alerta e retenção líquida da carteira.

## Riscos e limites
- Poucos eventos por carteira (dezenas de cancelamentos): os números são indicativos; a calibração melhora conforme o histórico cresce.
- O score não é uma probabilidade de cancelamento: ordena o atendimento, não prevê perda.
- Dependência da qualidade e da periodicidade dos dados enviados.
