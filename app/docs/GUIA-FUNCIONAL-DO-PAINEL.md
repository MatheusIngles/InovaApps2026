# Guia funcional do painel e das métricas

Este guia explica, em linguagem simples, os números e informações exibidos no Radar de Retenção. Ele descreve o funcionamento atual da aplicação. Os dados vêm da planilha de demonstração importada para o banco; o painel não recebe atualizações automáticas de sistemas externos.

## Comece por aqui

Depois de entrar, a página inicial abre a empresa ativa que está no topo da fila. Use a navegação para acessar **Painel** (`/painel`), **Empresas** (`/empresas`) ou **Assistente** (`/assistente`).

Uma leitura útil é:

1. No **Painel**, veja quantos clientes ativos têm score alto ou crítico e consulte a fila de atendimento.
2. Abra uma **empresa** da fila para entender os sinais que contribuíram para o score, o histórico e as ações sugeridas.
3. Use o **Assistente** para pedir um resumo ou fazer perguntas sobre uma empresa específica, identificada por um código como `C012`.

**O score de 0 a 100 é uma pontuação por regras, não uma probabilidade de cancelamento.** Um score de 60 não significa “60% de chance de cancelar”. A exposição em reais também é um indicador de ordenação, não uma previsão de perda financeira.

## De onde saem os dados

Para cada empresa, o cadastro informa código, segmento, porte, plano, valor mensal do contrato, SLA contratado, início e situação atual. O histórico mensal contém chamados, cumprimento do SLA, uso da plataforma, reclamações, atrasos de pagamento e reuniões. As pesquisas guardam a nota de satisfação e se houve resposta.

Os percentuais mensais de **SLA cumprido** e **uso da plataforma** são importados da planilha; a aplicação não os recalcula a partir dos chamados ou de eventos de uso. Já a proporção de reaberturas e a proporção de reuniões realizadas são calculadas com os totais dos meses considerados. Chamados críticos, tempo médio de resolução e SLA contratado são preservados na base, mas **não entram no score atual**.

O cálculo usa o **último mês com métricas disponível** para cada empresa. Para empresas canceladas, considera somente meses anteriores ao cancelamento. A avaliação é gravada com a versão `rules-v1`. Os valores apresentados só mudam quando os dados são importados novamente e a avaliação é recalculada.

### Termos usados no painel

| Termo | Significado |
|---|---|
| **Valor mensal** | Valor recorrente do contrato de uma empresa, em reais por mês. |
| **SLA cumprido** | Percentual de chamados atendidos dentro do prazo acordado. O painel mostra o percentual registrado na base. |
| **Uso da plataforma** | Percentual de uso informado para aquele mês. |
| **NPS / nota** | Nota individual de uma pesquisa, de 0 a 10. O painel não calcula o indicador agregado de NPS da carteira. |
| **Score de sinais** | Pontuação de 0 a 100 calculada pelas regras descritas abaixo. Quanto maior, mais sinais de atenção. |
| **Exposição mensal indicativa** | Score dividido por 100 e multiplicado pelo valor mensal do contrato. Serve para ordenar a fila. |
| **Nível** | Faixa visual do score: baixo, médio, alto ou crítico. |

## Como o score é calculado

O sistema observa os **três meses mais recentes** de métricas da empresa. Em várias medidas usa a **mediana**: o valor central quando os três meses são colocados em ordem. Isso reduz o efeito de um único mês fora do padrão. Chamados, reclamações e reuniões são somados nos três meses.

Cada sinal recebe uma intensidade entre **0** (não acrescenta pontos) e **1** (recebe o peso inteiro). Valores abaixo de 0 são tratados como 0; acima de 1, como 1. A intensidade é multiplicada pelo peso do sinal. Cada parcela é arredondada para uma casa decimal; a soma final é arredondada para um número inteiro.

| Sinal | Peso máximo | O que faz a pontuação subir |
|---|---:|---|
| Uso da plataforma | 20 | Uso recente abaixo de 85%. A intensidade é `(85 − mediana do uso) ÷ 35`. |
| SLA cumprido | 15 | SLA recente abaixo de 85%. A intensidade é `(85 − mediana do SLA) ÷ 45`. |
| Chamados reabertos | 10 | Maior proporção de reaberturas. A intensidade é `(reabertos ÷ abertos) ÷ 0,25`. |
| Reclamações formais | 10 | Mais reclamações nos três meses. A intensidade é `total de reclamações ÷ 4`. |
| Atraso de pagamento | 10 | Mais dias de atraso. A intensidade é `mediana dos dias ÷ 10`. |
| Reuniões realizadas | 12 | Menor proporção de reuniões realizadas. A intensidade é `(0,8 − realizadas ÷ previstas) ÷ 0,6`. |
| Pesquisas de satisfação | 15 | Nota baixa e/ou pesquisas sem resposta; veja a regra abaixo. |
| Tendência de uso | 8 | Uso recente abaixo da média dos meses anteriores. A intensidade é `queda em pontos percentuais ÷ 30`. |

Exemplo simples: se a intensidade do sinal de uso for `0,5`, ele soma `0,5 × 20 = 10` pontos. Os outros sete sinais também podem somar pontos, até o máximo conjunto de 100.

### Regras para casos específicos

- **Chamados:** se não houve chamados abertos nos três meses, a intensidade de reabertura é zero.
- **Reuniões:** se não havia reuniões previstas, esse sinal não acrescenta pontos; ausência de previsão não é tratada como reunião perdida.
- **SLA sem valor:** o histórico mostra `—` quando o dado mensal está ausente. No cálculo do score, a regra usa **100%** para esse mês ausente; portanto, a falta do dado não aumenta o alerta de SLA.
- **Pesquisas:** são consideradas as **três pesquisas mais recentes disponíveis até o mês avaliado**, não necessariamente pesquisas dos três meses recentes. Entre as respondidas, vale a nota da resposta mais recente. A intensidade do sinal é metade da intensidade da nota `((8 − nota) ÷ 6)` e metade da fração de pesquisas sem resposta. Se não existe nota, a parte da nota vale zero; se não há pesquisas, a parte de não resposta também vale zero. Cada parte é limitada ao intervalo de 0 a 1 antes da combinação.
- **Tendência:** compara a mediana de uso dos três meses recentes com a **média de todos os meses anteriores**. Se há no máximo três meses de histórico, o código usa o próprio histórico disponível como comparação; nesse caso, interprete a tendência com cautela.

### Níveis e sinais visíveis

| Score | Nível exibido |
|---:|---|
| 0 a 24 | Baixo |
| 25 a 39 | Médio |
| 40 a 54 | Alto |
| 55 a 100 | Crítico |

O detalhe da empresa mostra apenas sinais que atingiram **pelo menos 35% do peso daquele sinal**. Por exemplo, um sinal de peso 20 aparece a partir de 7 pontos. Os sinais aparecem em ordem decrescente de pontos. Assim, um score pode incluir pequenas parcelas que não estão listadas entre os destaques.

## Como a fila é ordenada

A **exposição mensal indicativa** combina o score com o valor do contrato:

```text
exposição mensal indicativa = score ÷ 100 × valor mensal
```

Se o score é **60** e o contrato vale **R$ 10.000 por mês**, a exposição exibida é **R$ 6.000**. Esse valor não quer dizer que R$ 6.000 serão perdidos, nem que a chance de cancelamento é 60%.

A fila do Painel mostra os **oito clientes ativos** com maior exposição. Na lista **Empresas**, clientes ativos vêm primeiro e são ordenados pela exposição; cancelados aparecem ao final. A lista permite buscar por código ou segmento e filtrar por nível e segmento.

## O que significa cada informação do Painel

| Informação | Como é obtida | Como interpretar |
|---|---|---|
| **Clientes ativos** | Contagem de empresas com situação `Ativo`. | Tamanho atual da carteira na base importada. |
| **Cancelaram no período** | Contagem de empresas com situação `Cancelado`. | Histórico da planilha, não cancelamentos ocorridos hoje. |
| **Receita mensal ativa** | Soma dos valores mensais dos clientes ativos. | Valor contratado por mês na carteira ativa. |
| **Score alto ou crítico** | Número de ativos com score igual ou superior a 40. | Contas que merecem revisão prioritária segundo as regras. |
| **R$/mês em jogo** | Soma dos contratos mensais desses ativos com score ≥ 40. | Valor total dos contratos do grupo, não exposição ponderada e não perda prevista. |
| **Receita já perdida** | Soma dos valores mensais dos clientes cancelados. A descrição anual multiplica essa soma por 12. | Referência de contratos cancelados; não é faturamento efetivamente perdido medido mês a mês. |
| **Clientes por nível** | Contagem de ativos em cada faixa do score. | Distribuição atual da carteira ativa. |
| **Score médio por segmento** | Média dos scores dos ativos em cada segmento. | Comparação descritiva entre grupos; grupos pequenos podem oscilar bastante. |
| **Uso × SLA** | Média mensal dos valores de todos os clientes que **hoje** estão ativos. | Tendência histórica desse grupo; o SLA ausente não entra na média daquele mês. |
| **Comparação exploratória** | Compara o último score anterior ao cancelamento com o último score dos ativos. | Ajuda a explorar diferenças, mas não é validação de previsão futura. |

## Como ler a página de uma empresa

- **Cabeçalho e Sobre:** mostram cadastro, contrato, score, nível e exposição. Empresas canceladas são identificadas como canceladas.
- **Em destaque:** cada sinal traz o motivo, os pontos acrescentados ao score e uma ação sugerida. As ações são textos definidos nas regras, para apoiar a conversa com o cliente.
- **Histórico mensal:** mostra, do mês mais recente para o mais antigo, chamados abertos e reabertos, SLA, uso, reclamações, dias de atraso e reuniões **realizadas/previstas**. `—` significa que o SLA não foi informado naquele mês.
- **Empresas parecidas que cancelaram:** até três exemplos com padrão de sinais semelhante. A semelhança compara os oito sinais: **100% significa vetores iguais**, não 100% de chance de cancelamento. Para uma avaliação histórica, só entram cancelamentos conhecidos até o mês avaliado.
- **Satisfação (NPS):** exibe mês e nota de cada pesquisa. Notas **0 a 6** aparecem como detratoras, **7 a 8** como neutras e **9 a 10** como promotoras. `—` significa que o cliente foi convidado e não respondeu; não equivale à nota zero.

## Como usar o Assistente

É possível selecionar uma empresa ou informar um código na pergunta. Sem empresa em foco, o assistente recebe um resumo da carteira e dos primeiros colocados na fila. Com uma empresa em foco, recebe contrato, score, sinais, exemplos similares, pesquisas recentes e parte do histórico mensal.

Quando habilitado, o chat tenta responder com um modelo de linguagem local ou com uma API configurada. Se nenhum deles responder, usa respostas locais por regras. A indicação abaixo da mensagem informa a origem da resposta. As respostas ajudam a interpretar os dados, mas devem ser conferidas com o histórico e com o responsável pela conta. O botão **Nova conversa** limpa as mensagens visíveis da conversa atual.

## Limites importantes para decisões

1. O score é calculado por **regras fixas**. A aplicação ainda não preenche a probabilidade estatística de cancelamento nem a receita esperada em risco de um modelo calibrado.
2. A faixa “alto” ou “crítico” é um critério de atenção. Ela não prova que o cliente cancelará.
3. A comparação com clientes cancelados e o percentual de semelhança não demonstram causa, nem garantem que uma ação terá o mesmo efeito em outra empresa.
4. O painel reflete a base importada. Se os registros mudarem, é preciso atualizar a importação e recalcular as avaliações para ver novos scores.
