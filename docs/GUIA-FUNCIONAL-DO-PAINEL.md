# Guia funcional: métricas, cálculos e telas

> **Nota:** este guia foi elaborado com base nos **dados de teste** (planilha de demonstração). Os exemplos, as colunas e o comportamento descritos partem desse conjunto e podem variar conforme a planilha e a configuração de cada empresa.

Este guia descreve o comportamento **atual do código** do Radar de Retenção. Ele serve para interpretar os números da carteira e localizar a origem de cada cálculo. Cada empresa usuária do sistema vê apenas sua própria carteira. Os dados vêm de planilhas importadas; não há atualização automática de sistemas externos.

## Por onde começar

Após o login, uma carteira com dados abre a lista **Clientes** (`/empresas`); uma carteira vazia abre a tela de envio da planilha. O **Painel** (`/painel`) vem depois na navegação. Na lista, use a busca por código ou segmento e os filtros de nível, segmento, situação, porte, plano, faixa de atenção e valor mensal. Abra um cliente para ver os sinais que compõem sua avaliação, o histórico e as ações sugeridas.

**Atenção não é probabilidade de cancelamento.** Uma atenção de 60/100 não significa 60% de chance de saída. A exposição mensal também é apenas um índice para ordenar o atendimento, não uma perda financeira prevista.

## Caminho dos dados

1. O envio aceita CSV ou XLSX (até 20 MB). O leitor identifica cabeçalhos e linhas. Um XLSX pode ter várias abas de dados, desde que cada uma tenha `cliente_id`: as abas com `mes_ref` definem as linhas cliente × mês e as demais (cadastro) valem para todos os meses do cliente. As abas "Leia-me" e "Dicionário" não são dados; o dicionário, quando existe, pré-preenche tipo, descrição e pesos das métricas. A planilha de demonstração, com quatro abas (`clientes`, `atendimento_mensal`, `pesquisas_nps` e `situacao_clientes`), é reunida por código do cliente e mês.
2. O sistema escolhe um de dois caminhos de importação:
   - **Formato padrão (oito sinais).** Vale quando a planilha traz todas as colunas do formato padrão (`cliente_id`, `mes_ref`, `segmento`, `porte`, `plano`, `valor_mensal`, chamados abertos e reabertos, % de SLA, reclamações, % de uso, dias de atraso, reuniões previstas e realizadas, `respondeu` e `nota_nps`) e a carteira ainda está vazia ou já usa esses sinais. Não há tela de mapeamento: as colunas são reconhecidas pelo nome (ou por apelidos como `mrr`, `uso`, `nps`). Grava o cadastro, uma linha por mês com as métricas de atendimento e uma pesquisa por mês quando presente.
   - **Métricas livres.** É o caminho de qualquer outra planilha. O usuário confere o mapeamento de seis campos estruturais (código, mês de referência, segmento, porte, plano e valor mensal; segmento e plano são opcionais e ficam "Não informado") e configura cada coluna restante como uma métrica: nome, descrição, tipo, direção, valores saudável e crítico e peso. As colunas `situacao`, `mes_cancelamento` e `inicio_contrato` são reconhecidas como cadastro e não viram métrica.

   Nos dois caminhos, reimportar o mesmo cliente e mês atualiza o registro em vez de duplicá-lo.
3. Depois da importação, o sistema recalcula as avaliações da carteira. Arquivos acima de 2 MB são processados por um trabalho em fila; a conclusão é informada por notificação. Na primeira carga de uma empresa que ainda não personalizou pesos nem métricas, o sistema também aplica uma configuração base calibrada pelos próprios dados, se houver cancelamentos suficientes para isso.
4. A avaliação `rules-v1` guarda atenção, exposição, sinais e exemplos semelhantes. As telas consultam a avaliação mais recente dessa versão. Alterações de pesos, limites ou métricas nas Configurações também disparam o recálculo.

A reimportação faz atualização ou inclusão dos registros enviados; ela não remove automaticamente meses antigos que deixaram de constar da nova planilha. No caminho de métricas livres, uma célula vazia de um mês reenviado **apaga** o valor antes gravado dessa métrica naquele mês. A avaliação é atualizada para o mês de referência encontrado, e avaliações antigas continuam armazenadas. Depois da primeira carga, novos meses entram pela aba **Acrescentar novos meses** em Configurações, com a mesma tela de importação.

Um cliente sem nenhum valor de métrica nos últimos três meses até o mês de referência não recebe avaliação. Na interface, a atenção de um cliente sem avaliação aparece como **0**, pois esse é o valor de apresentação usado pelo modelo; isso não equivale a uma avaliação calculada de risco baixo. O valor de exposição também aparece como zero.

A tabela abaixo descreve os dados do formato padrão, usado na planilha de demonstração, que serviu como caso de teste. Esse conjunto não é fixo: o mapeamento de colunas permite adaptar a planilha de cada carteira, e as métricas e os pesos que entram na atenção podem ser ajustados em Configurações.

### Dados de entrada e seu uso

| Informação | Origem | Uso atual |
|---|---|---|
| Código, segmento, porte, plano, valor mensal, início, situação e cancelamento | Cadastro importado | Identificação, filtros, receita, recorte de histórico e ordenação. |
| SLA contratado em horas | Cadastro importado (formato padrão) | Detalhes e contexto do assistente; não entra na atenção. No caminho de métricas livres fica zerado. |
| Chamados abertos e reabertos | Histórico mensal | Proporção de reaberturas na atenção. |
| Chamados críticos, dentro do SLA e tempo médio de resolução | Histórico mensal | Armazenados; entram na atenção como métricas extras quando a base tem esses dados (ajustáveis em Configurações › Métricas). |
| Percentual de SLA cumprido e uso da plataforma | Histórico mensal | Importados como percentuais prontos; usados na atenção e no gráfico. Não são recalculados a partir dos chamados ou eventos de uso. |
| Reclamações, dias de atraso e reuniões previstas/realizadas | Histórico mensal | Sinais da atenção. |
| Métricas próprias (qualquer outra coluna) | Histórico mensal | Entram na atenção conforme peso, direção e valores saudável/crítico definidos. Colunas de texto e data ficam só no histórico. |
| Pesquisa respondida e nota de 0 a 10 | Histórico mensal de pesquisas | Sinal de satisfação e exibição do histórico. O sistema não calcula o NPS agregado da carteira. |

**Células vazias no formato padrão.** Campos numéricos vazios de métricas mensais viram zero, com duas exceções: **uso da plataforma vazio** vira 100% e não acrescenta alerta de uso, e **SLA cumprido vazio** permanece ausente (mês sem chamados). No cálculo da atenção, um SLA mensal ausente é tratado como 100%; no histórico, aparece como “—”. Um mês só vira registro de métricas se ao menos uma das métricas mensais estiver preenchida. Uma pesquisa é gravada quando `respondeu` ou `nota_nps` vem preenchido; com nota e sem `respondeu`, conta como respondida.

**Células vazias em métricas livres.** Não viram zero: o valor fica ausente, não entra na mediana e, se o mês já tinha valor, ele é removido. Valores inválidos para o tipo (por exemplo, percentual acima de 100 ou nota fora de 0–10) recusam a importação inteira, com o número da linha. Se nenhuma métrica tiver valor, a importação também é recusada.

**Cadastro e duplicidades no formato padrão.** O importador usa o **primeiro valor preenchido** encontrado para os dados cadastrais de um cliente. Para duas linhas do mesmo cliente e mês, a última linha processada prevalece nas métricas ou na pesquisa daquele mês. Uma linha sem código é ignorada; um mês inválido é ignorado para as métricas, embora os dados cadastrais daquela linha ainda possam ser usados. Cliente cancelado sem mês de saída informado assume o mês seguinte ao último mês com métricas.

**Cadastro e duplicidades em métricas livres.** A validação é mais rígida: código, mês e valor mensal são obrigatórios em toda linha, e cliente e mês repetidos no mesmo arquivo recusam a importação. O cadastro (segmento, porte, plano, valor mensal) vem do **mês mais recente** do cliente. Sem coluna `situacao`, o cliente é Ativo; `Cancelado` exige `mes_cancelamento`, e `Ativo` não pode tê-lo. O valor mensal também é guardado mês a mês.

## Como se forma a avaliação

Para cada cliente, o sistema encontra o último mês com métricas. Se estiver cancelado, usa somente métricas de meses **anteriores à data de cancelamento**; assim, sua atenção representa o período anterior à saída. Considera todo o histórico elegível para a tendência e os **até três meses mais recentes** para os demais sinais. Pesquisas consideradas são as dos **três últimos meses do calendário até o mês avaliado** (não as três últimas pesquisas); sem datas nos dados, usam-se as três últimas. Métricas próprias usam a **mediana dos valores dos três meses do calendário** que terminam no mês avaliado, e só entram se houver ao menos um valor nessa janela.

Para uso, SLA e atraso, a medida dos meses recentes é a **mediana**: o valor do meio depois de ordenar até três números. Com dois valores, é a média dos dois. Chamados, reclamações e reuniões são somados antes de calcular suas proporções.

Cada sinal tem uma **intensidade** entre 0 e 1. Nas métricas próprias, ela é `(mediana − valor saudável) ÷ (valor crítico − valor saudável)`, o que funciona nas duas direções (piora quando o valor sobe ou quando desce). Intensidade 0 não soma pontos; intensidade 1 usa todo o peso. Resultados abaixo de zero são elevados a zero e acima de um são limitados a um.

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
atenção = arredondar(soma das parcelas, número inteiro)
```

A normalização mantém a atenção entre 0 e 100 mesmo quando os pesos configurados não somam 100. Por exemplo, intensidade 0,5 em um sinal de peso 20, com soma de pesos 100, acrescenta 10 pontos. Na página do cliente, cada linha separa a **parcela da métrica** (`intensidade × 100 ÷ número de sinais avaliados`, ou seja, `× 12,5` com os oito sinais padrão) do **ajuste pela prioridade** (`parcela final − parcela da métrica`). Esse ajuste pode ser positivo ou negativo; a soma das duas partes é a parcela final daquele sinal. Os sinais em destaque são aqueles cuja parcela final chega a **35% ou mais do máximo daquele sinal**; parcelas menores ainda entram na atenção.

Os níveis **padrão** são:

| Atenção | Nível |
|---:|---|
| 0–24 | Baixo |
| 25–39 | Médio |
| 40–54 | Alto |
| 55–100 | Crítico |

Em **Configurações** há as abas **Prioridades**, **Métricas**, **Fila de prioridade**, **Níveis de atenção**, **Identidade visual**, **Assistente de IA** e **Acrescentar novos meses**.

- **Prioridades** (só para carteiras com os oito sinais padrão): a lista é reordenada por arrastar, e o que fica no topo pesa mais. Os pesos pertencem às **posições** da lista, não às métricas: **Editar pesos** define o peso de cada posição (de 1 a 100, sem aumentar de uma posição para a seguinte). Um interruptor por sinal o tira do cálculo (peso 0, sem ocupar posição). Os valores são normalizados pela soma dos pesos ativos, então o peso configurado não é necessariamente o número de pontos finais.
- **Métricas:** cadastro das métricas próprias, com peso, direção e valores saudável e crítico. Chamados críticos, tempo médio de resolução e volume de chamados abertos entram aqui como métricas extras, ativadas automaticamente quando a planilha padrão tem esses dados.
- **Fila de prioridade:** o equilíbrio K, de 0 a 500 (padrão 50).
- **Níveis de atenção:** limites inteiros de 1 a 100, que precisam seguir `médio < alto < crítico`.

Salvar pesos, métricas ou limites recalcula a carteira; restaurar os padrões também recalcula.

**Atenção à diferença de critérios:** o cartão “Atenção ≥ 40” e os cartões da comparação exploratória usam o corte **fixo de 40 pontos**, mesmo quando os limites personalizados de Alto e Crítico mudam. Já o gráfico por nível, o rótulo do cliente e o filtro “Nível” usam os limites configurados. Assim, após uma personalização, a quantidade no cartão pode diferir da soma de Alto e Crítico no gráfico.

## Exposição e ordem de atendimento

```text
exposição mensal indicativa = atenção ÷ 100 × valor mensal do contrato
```

O resultado é arredondado para centavos e armazenado na avaliação. Se a atenção é 60 e o contrato vale R$ 10.000/mês, a exposição indicativa é R$ 6.000. Esse valor não é a receita efetivamente perdida nem uma probabilidade.

A fila no Painel mostra **até oito clientes ativos**, na mesma ordem da lista Clientes: primeiro quem já está em alerta (nível Médio ou acima) e, dentro de cada grupo, `atenção × (atenção + K) × valor mensal do contrato` (K = 50 por padrão, de 0 a 500, ajustável em Configurações › Fila de prioridade). Clientes marcados como resolvidos vão para o fim dos ativos, e os cancelados ficam por último. O prazo de contato vem da posição na fila. Clientes sem avaliação podem aparecer com atenção e exposição zero. O filtro **Nível de atenção** aceita Crítico, Alto, Médio, Baixo, Resolvido, Sem avaliação e Cancelado; os quatro primeiros consideram apenas clientes ativos não resolvidos. O filtro por situação permite selecionar ativos ou cancelados; filtros por atenção e valor mensal aceitam limites mínimo e máximo.

## O que cada área do Painel mostra

O Painel tem as abas **Visão geral** (indicadores, fila e evidências), **Gráficos** e, só para carteiras com os oito sinais padrão, **Por segmento**. O botão **Gerar relatório de evidências** cria um PDF em segundo plano e avisa por notificação. Parte dos cartões depende de a carteira ter os sinais padrão ou apenas métricas livres.

| Informação | Cálculo e interpretação |
|---|---|
| **Clientes ativos** | Contagem dos registros com situação `Ativo`. O subtítulo conta os registros `Cancelado` na base importada, sem filtro de datas. |
| **Receita mensal ativa** | Soma dos valores mensais dos contratos ativos. |
| **Métricas no cálculo** (só métricas livres) | Quantidade de métricas ativas, com peso maior que zero e que não sejam texto ou data. |
| **Atenção ≥ 40** | Contagem dos ativos com atenção **≥ 40**, independentemente dos limites personalizados. O subtítulo soma o **valor integral dos contratos** desses clientes, não a exposição ponderada. |
| **Contratos cancelados/mês** (só sinais padrão) | Soma dos valores mensais registrados para clientes cancelados. O subtítulo multiplica essa soma por 12; é uma referência, não faturamento perdido medido. |
| **Clientes ativos por nível de atenção** | Contagem de ativos em cada faixa definida pelos limites atuais da empresa. |
| **Atenção média por segmento** | Média aritmética das atenções dos ativos de cada segmento. Grupos pequenos podem oscilar bastante. |
| **Uso da plataforma × SLA cumprido** (só sinais padrão) | Para cada mês, média aritmética dos percentuais registrados dos clientes **atualmente ativos**. Valores ausentes de SLA não entram na média. O gráfico arredonda os pontos para inteiros; se todos os SLA de um mês estiverem ausentes, a conversão atual mostra **0**, que não deve ser lido como 0% de cumprimento. |
| **Atenção média por mês** (só métricas livres) | Média das avaliações dos clientes ativos em cada mês de referência. |
| **Comparação exploratória da atenção** (só sinais padrão) | Média das atenções de cancelados na última avaliação anterior à saída contra a média dos ativos na avaliação mais recente. As contagens “≥ 40” também usam corte fixo. É uma descrição da base, não validação de previsão futura. |
| **Fila de atendimento** | Até oito ativos, na ordem da fila (alerta primeiro e, em cada grupo, atenção × (atenção + K) × valor). O “principal motivo” é o sinal visível com mais pontos; pode aparecer “Sem sinal forte” mesmo quando a atenção inclui parcelas pequenas. |

## Como ler a página de um cliente

A página tem três abas: **Visão geral**, **Histórico mensal** e **Tendência e previsão**.

- **Indicadores atuais:** contrato mensal e exposição da **última avaliação**; nas carteiras com sinais padrão, também uso e SLA do **último mês armazenado no histórico**; nas de métricas livres, o número de métricas configuradas e observadas e de meses importados. Para um cliente cancelado, esse último mês exibido pode ser diferente do mês usado na atenção, pois o histórico da tela não aplica o recorte anterior ao cancelamento.
- **Sinais em destaque:** motivo, pontos e ação sugerida definidos nas regras. Para cancelados, aparecem os sinais anteriores à saída, sem o texto de ação.
- **Previsão da atenção:** reta de mínimos quadrados sobre a atenção dos últimos até seis meses (mínimo de três), projetada para o mês seguinte, com uma faixa de erro. Mostra para onde a série caminha se o ritmo recente continuar; **não é probabilidade de cancelamento** e não aparece para cancelados.
- **Composição da atenção:** mostra as oito parcelas, incluindo as pequenas, com a parte neutra da métrica e o ajuste provocado pela prioridade configurada.
- **Histórico mensal:** do mais recente para o mais antigo; nas carteiras com métricas livres, lista os valores de cada métrica; nas com sinais padrão, mostra chamados, reaberturas, SLA, uso, reclamações, dias de atraso e reuniões realizadas/previstas. “—” no SLA significa dado não informado.
- **Empresas parecidas que cancelaram:** até três clientes cancelados da mesma carteira, ordenados pela semelhança dos oito sinais. A semelhança é baseada na distância entre as intensidades; **100% significa intensidades iguais**, não chance de cancelamento. Para uma avaliação histórica, não entram saídas posteriores ao mês avaliado.
- **Comparação com cancelados e retidos:** gravidade atual de cada sinal ao lado da média de quem ficou e de quem cancelou.
- **Satisfação (NPS), nas carteiras com sinais padrão:** apresenta cada pesquisa armazenada, inclusive as sem resposta (“—”). Notas 0–6 são marcadas como detratoras, 7–8 como neutras e 9–10 como promotoras. A tela pode mostrar pesquisas posteriores ao mês usado na atenção de um cancelado; o cálculo da atenção, porém, só usa pesquisas até o mês avaliado.

## Assistente e limites de uso

O assistente pode receber um resumo da carteira ou o contexto de um cliente, com atenção, contrato, sinais, pesquisas e histórico. Quando habilitado, tenta usar um modelo local ou uma API configurada; se nenhum responder, usa respostas por regras. A origem da resposta aparece na conversa. Confira recomendações com o histórico e a pessoa responsável pela conta.

O sistema ainda não calcula `risk_probability` (probabilidade estatística de cancelamento), `expected_revenue_at_risk` (receita esperada em risco) nem `confidence`: esses campos ficam vazios na avaliação `rules-v1`. A atenção usa regras fixas e a comparação com cancelados não estabelece causa ou capacidade de previsão futura.

## Arquivos responsáveis

| Etapa | Arquivos principais |
|---|---|
| Leitura de CSV/XLSX, junção de abas e dicionário | `app/Support/Import/PlanilhaReader.php` |
| Formato padrão: reconhecimento de colunas, normalização e gravação de clientes, meses e pesquisas | `app/Support/Import/ImportService.php` |
| Métricas livres: mapeamento estrutural, validação e gravação de valores | `app/Support/Import/DynamicImportService.php` |
| Modelo XLSX para download | `app/Support/Import/ModeloPlanilha.php` |
| Interface de envio e execução em fila | `app/Livewire/ImportarPlanilha.php`, `app/Jobs/ImportarPlanilhaJob.php` |
| Fórmulas, pesos padrão, sinais, níveis e semelhança | `app/Support/Risco.php` |
| Métricas próprias na atenção e métricas extras dos chamados | `app/Support/Metricas/MetricRisk.php`, `app/Support/Metricas/SinaisExtras.php` |
| Previsão da atenção do próximo mês | `app/Support/Validacao/Previsao.php` |
| Recorte temporal, recálculo, exposição e persistência da avaliação | `app/Support/RiskService.php` |
| Pesos e limites por empresa, validação e restauração | `app/Models/Company.php`, `app/Support/Tenancy/CompanyConfig.php`, `app/Filament/Pages/Configuracoes.php` |
| Leitura da avaliação mais recente e apresentação de campos do cliente | `app/Models/Customer.php`, `app/Models/RiskAssessment.php` |
| KPIs, gráficos, comparação e fila | `app/Filament/Widgets/KpisWidget.php`, `NiveisChart.php`, `SegmentosChart.php`, `TendenciaChart.php`, `BacktestWidget.php`, `FilaTable.php` (na mesma pasta) |
| Lista, filtros e perfil do cliente | `app/Filament/Resources/Empresas/EmpresaResource.php`, `resources/views/filament/pages/empresa.blade.php` |
| Carga de demonstração | `database/seeders/CustomerDataSeeder.php`, `database/seeders/RiskAssessmentSeeder.php` |

Os caminhos da tabela são relativos à pasta `app/` do repositório.
