# Guia funcional: métricas, cálculos e telas

Este guia descreve o comportamento **atual do código** do Radar de Retenção. Ele serve para interpretar os números da carteira e localizar a origem de cada cálculo. Cada empresa usuária do sistema vê apenas sua própria carteira. Os dados vêm de planilhas importadas; não há atualização automática de sistemas externos.

## Por onde começar

Após o login, uma carteira com dados abre a lista **Clientes** (`/empresas`); uma carteira vazia abre a tela de envio da planilha. O **Painel** (`/painel`) vem depois na navegação. Na lista, use a busca por código ou segmento e os filtros de nível, segmento, situação, porte, plano, faixa de score e valor mensal. Abra um cliente para ver os sinais que compõem sua avaliação, o histórico e as ações sugeridas.

**Score não é probabilidade de cancelamento.** Um score de 60/100 não significa 60% de chance de saída. A exposição mensal também é apenas um índice para ordenar o atendimento, não uma perda financeira prevista.

## Caminho dos dados

1. O envio aceita CSV ou XLSX. O leitor identifica cabeçalhos e linhas. A planilha de demonstração, com quatro abas (`clientes`, `atendimento_mensal`, `pesquisas_nps` e `situacao_clientes`), é reunida por código do cliente e mês.
2. O usuário confere o mapeamento de colunas. A importação grava o cadastro de cada cliente, uma linha por mês com métricas de atendimento e uma pesquisa por mês quando presente. Reimportar o mesmo cliente e mês atualiza o registro em vez de duplicá-lo.
3. Depois da importação, o sistema recalcula as avaliações da carteira. Arquivos acima de 2 MB são processados por um trabalho em fila; a conclusão é informada por notificação.
4. A avaliação `rules-v1` guarda score, exposição, sinais e exemplos semelhantes. As telas consultam a avaliação mais recente dessa versão. Alterações de pesos ou limites nas Configurações também disparam o recálculo.

A reimportação faz atualização ou inclusão dos registros enviados; ela não remove automaticamente meses antigos que deixaram de constar da nova planilha. A avaliação é atualizada para o mês de referência encontrado, e avaliações antigas continuam armazenadas.

Um cliente sem histórico mensal de métricas não recebe avaliação. Na interface, o score de um cliente sem avaliação aparece como **0**, pois esse é o valor de apresentação usado pelo modelo; isso não equivale a uma avaliação calculada de risco baixo. O valor de exposição também aparece como zero.

### Dados de entrada e seu uso

| Informação | Origem | Uso atual |
|---|---|---|
| Código, segmento, porte, plano, valor mensal, início, situação e cancelamento | Cadastro importado | Identificação, filtros, receita, recorte de histórico e ordenação. |
| SLA contratado em horas | Cadastro importado | Detalhes e contexto do assistente; não entra no score. |
| Chamados abertos e reabertos | Histórico mensal | Proporção de reaberturas no score. |
| Chamados críticos, dentro do SLA e tempo médio de resolução | Histórico mensal | Armazenados, mas não entram no score atual. |
| Percentual de SLA cumprido e uso da plataforma | Histórico mensal | Importados como percentuais prontos; usados no score e no gráfico. Não são recalculados a partir dos chamados ou eventos de uso. |
| Reclamações, dias de atraso e reuniões previstas/realizadas | Histórico mensal | Sinais do score. |
| Pesquisa respondida e nota de 0 a 10 | Histórico mensal de pesquisas | Sinal de satisfação e exibição do histórico. O sistema não calcula o NPS agregado da carteira. |

No importador, campos numéricos vazios geralmente viram zero. As duas exceções relevantes são **uso da plataforma vazio**, que vira 100% e não acrescenta alerta de uso, e **SLA cumprido vazio**, que permanece ausente. No cálculo do score, um SLA mensal ausente é tratado como 100%; no histórico, aparece como “—”.

O importador usa o **primeiro valor preenchido** encontrado para os dados cadastrais de um cliente. Para duas linhas do mesmo cliente e mês, a última linha processada prevalece nas métricas ou na pesquisa daquele mês. Uma linha sem código é ignorada; um mês inválido é ignorado para as métricas, embora os dados cadastrais daquela linha ainda possam ser usados.

## Como se forma a avaliação

Para cada cliente, o sistema encontra o último mês com métricas. Se estiver cancelado, usa somente métricas de meses **anteriores à data de cancelamento**; assim, seu score representa o período anterior à saída. Considera todo o histórico elegível para a tendência e os **até três meses mais recentes** para os demais sinais. Pesquisas consideradas são as **até três mais recentes até o mês avaliado**; elas não precisam coincidir com os três meses de métricas.

Para uso, SLA e atraso, a medida dos meses recentes é a **mediana**: o valor do meio depois de ordenar até três números. Com dois valores, é a média dos dois. Chamados, reclamações e reuniões são somados antes de calcular suas proporções.

Cada sinal tem uma **intensidade** entre 0 e 1. Intensidade 0 não soma pontos; intensidade 1 usa todo o peso. Resultados abaixo de zero são elevados a zero e acima de um são limitados a um.

| Sinal | Peso padrão | Intensidade antes do limite de 0 a 1 |
|---|---:|---|
| Uso da plataforma | 20 | `(85 − mediana do uso recente) ÷ 35` |
| SLA cumprido | 15 | `(85 − mediana do SLA recente) ÷ 45` |
| Chamados reabertos | 10 | `(total reaberto ÷ total aberto) ÷ 0,25` |
| Reclamações formais | 10 | `total de reclamações ÷ 4` |
| Atraso de pagamento | 10 | `mediana dos dias de atraso ÷ 10` |
| Reuniões realizadas | 12 | `(0,8 − realizadas ÷ previstas) ÷ 0,6` |
| Satisfação por pesquisas | 15 | `50% × intensidade da nota + 50% × fração sem resposta` |
| Tendência de uso | 8 | `(média de uso anterior − mediana de uso recente) ÷ 30` |

A intensidade da nota é `(8 − última nota respondida) ÷ 6`, limitada de 0 a 1. Se não há nota respondida, essa metade vale zero. A fração sem resposta é a quantidade de pesquisas não respondidas dividida pela quantidade de pesquisas consideradas; sem pesquisas, vale zero. Uma nota individual de 0 a 10 é chamada de “NPS” nas telas, mas não é o índice agregado de NPS.

Há um caso de qualidade de dados que merece atenção: uma pesquisa marcada como **respondida**, mas sem nota, é convertida em nota **zero** pela regra atual. Antes de importar, confira se “respondida” e “nota” são coerentes.

Sem chamados abertos, a parcela de reabertura é zero. Sem reuniões previstas, a parcela de reuniões é zero. Para a tendência, “anterior” significa **todos os meses antes dos três recentes**; quando o histórico tem no máximo três meses, o próprio histórico disponível é usado como comparação. Portanto, a tendência com pouco histórico deve ser interpretada com cautela.

### Pesos, arredondamento e níveis

Com os pesos padrão, que somam 100, cada parcela é `intensidade × peso`. Com pesos personalizados, o cálculo é:

```text
parcela do sinal = arredondar(intensidade × peso do sinal ÷ soma dos pesos ativos × 100, 1 casa)
score = arredondar(soma das parcelas, número inteiro)
```

A normalização mantém o score entre 0 e 100 mesmo quando os pesos configurados não somam 100. Por exemplo, intensidade 0,5 em um sinal de peso 20, com soma de pesos 100, acrescenta 10 pontos. Os sinais visíveis nos detalhes são aqueles cuja parcela chega a **35% ou mais do máximo daquele sinal**; parcelas menores ainda entram no score. Os sinais visíveis são ordenados pelos pontos, com a ordem configurada dos pesos como desempate.

Os níveis **padrão** são:

| Score | Nível |
|---:|---|
| 0–24 | Baixo |
| 25–39 | Médio |
| 40–54 | Alto |
| 55–100 | Crítico |

Em **Configurações**, cada empresa pode reordenar e desligar sinais. A posição dos sinais ativos distribui a escala fixa de pesos `20, 15, 15, 12, 10, 10, 10, 8`; sinais desligados recebem peso zero e os pesos ativos são normalizados. Também é possível mudar os limites dos níveis, desde que `médio < alto < crítico`. Ao salvar uma mudança de pesos ou limites, o sistema recalcula a carteira. Restaurar os padrões também recalcula. A cor e o nome do nível exibido usam os limites atuais da empresa.

**Atenção à diferença de critérios:** o cartão “Score alto ou crítico” e os cartões da comparação exploratória usam o corte **fixo de 40 pontos**, mesmo quando os limites personalizados de Alto e Crítico mudam. Já o gráfico por nível, o rótulo do cliente e o filtro “Nível” usam os limites configurados. Assim, após uma personalização, a quantidade no cartão pode diferir da soma de Alto e Crítico no gráfico.

## Exposição e ordem de atendimento

```text
exposição mensal indicativa = score ÷ 100 × valor mensal do contrato
```

O resultado é arredondado para centavos e armazenado na avaliação. Se o score é 60 e o contrato vale R$ 10.000/mês, a exposição indicativa é R$ 6.000. Esse valor não é a receita efetivamente perdida nem uma probabilidade.

A fila no Painel mostra **até oito clientes ativos**, em ordem decrescente de exposição. A lista Clientes coloca os ativos primeiro, também por exposição, e os cancelados ao final. Clientes sem avaliação podem aparecer com score e exposição zero. O filtro por nível considera apenas ativos, exceto pela opção “Cancelada”. O filtro por situação permite selecionar ativos ou cancelados; filtros por score e valor mensal aceitam limites mínimo e máximo.

## O que cada área do Painel mostra

| Informação | Cálculo e interpretação |
|---|---|
| **Clientes ativos** | Contagem dos registros com situação `Ativo`. O subtítulo conta os registros `Cancelado` na base; “no período” se refere ao conjunto importado, sem filtro de datas. |
| **Receita mensal ativa** | Soma dos valores mensais dos contratos ativos. |
| **Score alto ou crítico** | Contagem dos ativos com score **≥ 40**, independentemente dos limites personalizados. O subtítulo “R$/mês em jogo” soma o **valor integral dos contratos** desses clientes, não a exposição ponderada. |
| **Receita já perdida** | Soma dos valores mensais registrados para clientes cancelados. O subtítulo anual multiplica essa soma por 12; não mede faturamento perdido mês a mês. |
| **Clientes ativos por nível** | Contagem de ativos em cada faixa definida pelos limites atuais da empresa. |
| **Risco médio por segmento** | Média aritmética dos scores dos ativos de cada segmento, arredondada para inteiro. Grupos pequenos podem oscilar bastante. |
| **Uso da plataforma × SLA cumprido** | Para cada mês, média aritmética dos percentuais registrados dos clientes **atualmente ativos**. Valores ausentes de SLA não entram na média SQL do mês. O gráfico arredonda os pontos para inteiros; se todos os SLA de um mês estiverem ausentes, a conversão atual mostra **0** no gráfico, que não deve ser interpretado como 0% de cumprimento. |
| **Comparação exploratória do score** | Média dos scores de cancelados na última avaliação anterior à saída contra a média dos ativos na avaliação mais recente. As contagens “≥ 40” também usam corte fixo. É uma descrição da base, não validação de previsão futura. |
| **Fila de atendimento** | Até oito ativos com maior exposição indicativa. O “principal motivo” é o sinal visível com mais pontos; pode aparecer “Sem sinal forte” mesmo quando o score inclui parcelas pequenas. |

## Como ler a página de um cliente

- **Indicadores atuais:** contrato mensal, uso e SLA do **último mês armazenado no histórico**, e exposição da **última avaliação**. Para um cliente cancelado, esse último mês exibido pode ser diferente do mês usado no score, pois o histórico da tela não aplica o recorte anterior ao cancelamento.
- **Sinais em destaque:** motivo, pontos e ação sugerida definidos nas regras. Para cancelados, aparecem os sinais anteriores à saída, sem o texto de ação.
- **Histórico mensal:** do mais recente para o mais antigo; mostra chamados, reaberturas, SLA, uso, reclamações, dias de atraso e reuniões realizadas/previstas. “—” no SLA significa dado não informado.
- **Empresas parecidas que cancelaram:** até três clientes cancelados da mesma carteira, ordenados pela semelhança dos oito sinais. A semelhança é baseada na distância entre as intensidades; **100% significa intensidades iguais**, não chance de cancelamento. Para uma avaliação histórica, não entram saídas posteriores ao mês avaliado.
- **Satisfação (NPS):** apresenta cada pesquisa armazenada, inclusive as sem resposta (“—”). Notas 0–6 são marcadas como detratoras, 7–8 como neutras e 9–10 como promotoras. A tela pode mostrar pesquisas posteriores ao mês usado no score de um cancelado; o cálculo do score, porém, só usa pesquisas até o mês avaliado.

## Assistente e limites de uso

O assistente pode receber um resumo da carteira ou o contexto de um cliente, com score, contrato, sinais, pesquisas e histórico. Quando habilitado, tenta usar um modelo local ou uma API configurada; se nenhum responder, usa respostas por regras. A origem da resposta aparece na conversa. Confira recomendações com o histórico e a pessoa responsável pela conta.

O sistema ainda não calcula `risk_probability` (probabilidade estatística de cancelamento), `expected_revenue_at_risk` (receita esperada em risco) nem `confidence`: esses campos ficam vazios na avaliação `rules-v1`. O score usa regras fixas e a comparação com cancelados não estabelece causa ou capacidade de previsão futura.

## Arquivos responsáveis

| Etapa | Arquivos principais |
|---|---|
| Leitura de CSV/XLSX e reunião das abas | `app/Support/Import/PlanilhaReader.php` |
| Mapeamento, normalização e gravação de clientes, meses e pesquisas | `app/Support/Import/ImportService.php` |
| Interface de envio e execução em fila | `app/Livewire/ImportarPlanilha.php`, `app/Jobs/ImportarPlanilhaJob.php` |
| Fórmulas, pesos padrão, sinais, níveis e semelhança | `app/Support/Risco.php` |
| Recorte temporal, recálculo, exposição e persistência da avaliação | `app/Support/RiskService.php` |
| Pesos e limites por empresa, validação e restauração | `app/Models/Company.php`, `app/Support/Tenancy/CompanyConfig.php`, `app/Filament/Pages/Configuracoes.php` |
| Leitura da avaliação mais recente e apresentação de campos do cliente | `app/Models/Customer.php`, `app/Models/RiskAssessment.php` |
| KPIs, gráficos, comparação e fila | `app/Filament/Widgets/KpisWidget.php`, `NiveisChart.php`, `SegmentosChart.php`, `TendenciaChart.php`, `BacktestWidget.php`, `FilaTable.php` (na mesma pasta) |
| Lista, filtros e perfil do cliente | `app/Filament/Resources/Empresas/EmpresaResource.php`, `resources/views/filament/pages/empresa.blade.php` |
| Carga de demonstração | `database/seeders/CustomerDataSeeder.php`, `database/seeders/RiskAssessmentSeeder.php` |

Os caminhos da tabela são relativos à pasta `app/` do repositório.
