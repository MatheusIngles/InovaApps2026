# Seer: arquitetura e funcionamento

> Documentação técnica e funcional baseada no estado atual do código-fonte. O documento descreve o que está implementado, incluindo as limitações conhecidas; não representa funcionalidades futuras.

## 1. Visão geral

O Seer é um painel interno para gestão de carteira de clientes, acompanhamento de saúde da conta e priorização de atendimento. A aplicação combina dados cadastrais, métricas mensais, pesquisas de satisfação e avaliações de risco calculadas por regras.

O sistema responde a três perguntas principais:

1. Quais clientes estão ativos e qual é o nível de risco de cada um?
2. Quais clientes devem receber atenção primeiro, considerando a atenção e o valor mensal?
3. Quais sinais explicam a atenção e qual ação operacional é recomendada?

O produto não é, no estado atual, um modelo estatístico de previsão de churn. A atenção é determinística, baseada em regras explícitas, e a comparação com clientes cancelados é exploratória.

## 2. Stack e dependências

- PHP `^8.3`
- Laravel `^13.17`
- Filament `^5.8`
- Livewire `^4.4`
- Vite `^8`
- Tailwind CSS `^4`
- OpenSpout `^4.32`, para leitura da planilha XLSX
- PHPUnit `^12.5`
- SQLite como banco padrão do ambiente atual

A configuração de dependências está em [composer.json](../app/composer.json) e [package.json](../app/package.json). O frontend usa os componentes do Filament, Livewire e Alpine; não há JavaScript próprio relevante em `resources/js/app.js`.

## 3. Arquitetura da aplicação

### 3.1 Camadas

```text
Usuário autenticado
        |
        v
Filament Panel (rota raiz /)
        |
        +-- Dashboard e widgets
        +-- EmpresaResource (lista e detalhe)
        +-- Página Assistente + Livewire AssistenteChat
        |
        v
Models Eloquent / queries de dashboard
        |
        +-- Customer
        +-- MetricDefinition / MetricValue
        +-- CustomerNps
        +-- RiskAssessment
        |
        v
SQLite / banco configurado pelo Laravel

Importação:
CSV/XLSX -> ImportService ou DynamicImportService
         -> customers, customer_periods, metric_definitions, metric_values, customer_nps
         -> RiskService -> risk_assessments
```

### 3.2 Bootstrap e painel

- [bootstrap/app.php](../app/bootstrap/app.php) registra o health check `/up`.
- [AppPanelProvider.php](../app/app/Providers/Filament/AppPanelProvider.php) configura o painel Filament.
- O painel usa caminho vazio, portanto atende a raiz `/`.
- O painel exige autenticação, sessão, CSRF, binding de rotas e autenticação de sessão.
- O painel permite login, cadastro e recuperação de senha.
- O nome visual do produto é `Seer`.
- O tema atual é claro, com azul como cor primária e Slate como cor neutra.
- O hook `BODY_END` injeta o widget externo do VLibras.

[routes/web.php](../app/routes/web.php) não declara rotas de negócio; as rotas são descobertas e registradas pelo Filament.

### 3.3 Rotas efetivas

- `/`: dashboard do painel
- `/login`: login
- `/register`: cadastro
- `/password-reset`: recuperação de senha
- `/empresas`: lista de empresas
- `/empresas/{record}`: detalhe de uma empresa; o record usa `external_code`
- `/assistente`: página do assistente
- `/up`: health check do Laravel

Não existe API própria, prefixo `/api`, controller de domínio ou endpoint público implementado.

### 3.4 Autorização

Multi-tenancy: todo usuário pertence a uma empresa (`company_id`). O middleware [SetCompanyContext.php](../app/app/Http/Middleware/SetCompanyContext.php) define a empresa ativa (a do usuário; em telas públicas, por subdomínio ou `?empresa=`, via [TenantResolver.php](../app/app/Support/Tenancy/TenantResolver.php)) e o trait `BelongsToCompany` filtra toda consulta e grava o `company_id` em toda inserção. Dados, configuração, chat e relatórios são isolados por empresa. Dentro da empresa, todo usuário autenticado tem acesso integral: não há perfis, papéis nem permissões por equipe.

## 4. Organização do código

- `app/Models`: modelos Eloquent e relações.
- `app/Support`: regras de risco e respostas do assistente.
- `app/Filament/Resources`: recursos CRUD/consulta do Filament.
- `app/Filament/Pages`: páginas customizadas do painel.
- `app/Filament/Widgets`: KPIs, gráficos e fila de atendimento.
- `app/Livewire`: estado e interação do chat.
- `database/migrations`: estrutura versionada do banco.
- `database/seeders`: importação e cálculo inicial dos dados.
- `../dados`: enunciado, base de exemplo e planilha de teste (fora da pasta do Laravel).
- `resources/views`: templates Blade do assistente, modal e acessibilidade.
- `tests`: testes de feature e unitários.

O controller base em [Controller.php](../app/app/Http/Controllers/Controller.php) não contém lógica de domínio.

## 5. Modelo de domínio

### 5.1 Customer

Arquivo: [Customer.php](../app/app/Models/Customer.php)

Representa uma empresa/cliente da carteira.

Relações:

- `hasMany(CustomerNps::class)` em `npsResponses`.
- `hasMany(RiskAssessment::class)` em `riskAssessments`.
- `hasOne(RiskAssessment::class)` em `currentAssessment`, filtrado por `rules-v1` e selecionando o mês mais recente.

Comportamentos importantes:

- A chave de rota é `external_code`, por exemplo `C012`.
- `dashboard()` junta o cliente à avaliação mais recente da versão `rules-v1`.
- `ativas()` retorna somente clientes com status `Ativo`.
- `ordenar()` coloca ativos antes de cancelados e ordena pela exposição decrescente.
- `score` vem de `health_score`.
- `exposicao` vem de `exposure_indicator`.
- `nivel` é derivado pelos limiares de [Risco.php](../app/app/Support/Risco.php).
- `sinais` e `similares` são lidos de `signals_json`.
- `hist` e `nps` transformam os dados persistidos em estruturas próprias para a tela.

A função `brl()` exibe valores como moeda brasileira sem casas decimais, por exemplo `R$ 12.500`.

### 5.2 Observações mensais

As observações de indicadores são armazenadas por cliente e mês. O conjunto de colunas varia conforme a planilha e é convertido em definições e valores de métricas pela importação dinâmica. Cada valor fica ligado à empresa, ao cliente, à definição da métrica e ao mês de referência.

### 5.3 CustomerNps

Arquivo: [CustomerNps.php](../app/app/Models/CustomerNps.php)

Representa uma pesquisa mensal de satisfação. O par `customer_id + reference_month` é único.

Campos:

- `answered`: indica se houve resposta;
- `score`: nota, anulável quando não houve resposta;
- `classification`: classificação persistida, quando disponível.

Pesquisas sem resposta continuam registradas e influenciam a severidade de NPS como comportamento de silêncio.

### 5.4 RiskAssessment

Arquivo: [RiskAssessment.php](../app/app/Models/RiskAssessment.php)

Armazena o resultado calculado para um cliente, mês de referência e versão de modelo. O índice único é `customer_id + reference_month + model_version`.

Campos relevantes:

- `health_score`: índice de atenção por regras entre 0 e 100;
- `exposure_indicator`: exposição financeira mensal indicativa;
- `risk_probability`: reservado para probabilidade estatística; atualmente nulo;
- `expected_revenue_at_risk`: reservado para receita esperada em risco; atualmente nulo;
- `confidence`: reservado para confiança do modelo; atualmente nulo;
- `signals_json`: evidências, severidades e clientes similares;
- `recommended_action_json`: reservado; as ações atuais ficam dentro das evidências;
- `model_version`: atualmente `rules-v1`;
- `calculated_at`: data/hora do cálculo.

### 5.5 MetricDefinition e MetricValue

Arquivos: [MetricDefinition.php](../app/app/Models/MetricDefinition.php) e [MetricValue.php](../app/app/Models/MetricValue.php)

`MetricDefinition` descreve uma métrica configurada pela empresa. Cada definição informa código, nome, descrição, tipo, direção, valor saudável, valor crítico, peso e se está ativa. O conjunto de definições varia por empresa e não é limitado a uma lista fixa de indicadores.

`MetricValue` guarda a observação de uma métrica para um cliente e mês. O par `customer_id + metric_definition_id + reference_month` é único. Tipos numéricos podem participar da atenção; texto e data ficam disponíveis para histórico e contexto, mas não pontuam.

## 6. Banco de dados

Todas as foreign keys de dados de clientes usam `cascadeOnDelete`: ao remover um cliente, suas métricas, pesquisas e avaliações são removidas.

### 6.1 Tabela `customers`

Migration: [2026_09_19_132055_create_customers_table.php](../app/database/migrations/2026_09_19_132055_create_customers_table.php)

| Campo | Tipo | Regra/uso |
|---|---|---|
| `id` | bigint | chave primária |
| `external_code` | string | código externo único, usado na URL |
| `segment` | string | segmento/setor |
| `size` | string | porte |
| `plan` | string | plano contratado |
| `monthly_value` | decimal(12,2) | valor mensal do contrato |
| `contracted_sla_hours` | unsigned smallint | SLA contratado em horas |
| `contract_started_at` | date | início do contrato |
| `status` | string | normalmente `Ativo` ou `Cancelado` |
| `cancelled_at` | date nullable | data do cancelamento |
| `created_at`, `updated_at` | timestamps | controle Laravel |

### 6.2 Tabelas `metric_definitions` e `metric_values`

As métricas são definidas por empresa e observadas por cliente e mês. `metric_definitions` guarda a configuração do indicador; `metric_values` guarda o valor numérico ou textual importado.

| Tabela | Regra/uso |
|---|---|
| `metric_definitions` | Uma definição por código dentro da empresa, com tipo, direção, limites saudável/crítico, peso e status ativo. |
| `metric_values` | Uma observação por cliente, métrica e mês; valores numéricos podem participar da atenção. |
| `customer_id + metric_definition_id + reference_month` | Chave lógica que evita duplicidade de observação. |

### 6.3 Tabela `customer_nps`

Migration: [2026_09_19_132057_create_customer_nps_table.php](../app/database/migrations/2026_09_19_132057_create_customer_nps_table.php)

| Campo | Tipo | Regra/uso |
|---|---|---|
| `customer_id` | foreign key | cliente proprietário |
| `reference_month` | date | mês de referência |
| `answered` | boolean | houve resposta? |
| `score` | unsigned tinyint nullable | nota quando respondido |
| `classification` | string nullable | classificação da pesquisa |
| `customer_id + reference_month` | unique | uma pesquisa por cliente/mês |

### 6.4 Tabela `risk_assessments`

Migration inicial: [2026_09_19_132059_create_risk_assessments_table.php](../app/database/migrations/2026_09_19_132059_create_risk_assessments_table.php).

A migration [2026_09_19_134141_add_rule_score_to_risk_assessments_table.php](../app/database/migrations/2026_09_19_134141_add_rule_score_to_risk_assessments_table.php) adiciona `health_score` e torna `risk_probability` e `expected_revenue_at_risk` anuláveis, refletindo a implementação atual baseada em regras.

| Campo | Tipo | Regra/uso atual |
|---|---|---|
| `customer_id` | foreign key | cliente avaliado |
| `reference_month` | date | último mês considerado |
| `health_score` | unsigned tinyint nullable | atenção efetiva, 0 a 100 |
| `risk_probability` | decimal(7,6) nullable | não calculado |
| `exposure_indicator` | decimal(14,2) nullable | exposição indicativa |
| `expected_revenue_at_risk` | decimal(14,2) nullable | não calculado |
| `confidence` | string nullable | não calculado |
| `signals_json` | json nullable | evidências, severidades e similares |
| `recommended_action_json` | json nullable | não preenchido pelo seeder atual |
| `model_version` | string | atualmente `rules-v1` |
| `calculated_at` | timestamp | instante do cálculo |
| `customer_id + reference_month + model_version` | unique | evita duplicidade da avaliação |

### 6.5 Tabelas de infraestrutura Laravel

As migrations padrão criam:

- `users`, `password_reset_tokens` e `sessions`;
- `cache` e `cache_locks`;
- `jobs`, `job_batches` e `failed_jobs`.

No ambiente padrão, sessão, cache e filas estão configurados para usar o banco.

## 7. Fluxo de dados e carga inicial

### 7.1 Fonte

A fonte principal é [INOVAAPPS_base_de_dados.xlsx](../dados/INOVAAPPS_base_de_dados.xlsx). O importador espera as abas:

- `clientes`;
- `atendimento_mensal`;
- `pesquisas_nps`;
- `situacao_clientes`.

### 7.2 Importação

[ImportService.php](../app/app/Support/Import/ImportService.php) e [DynamicImportService.php](../app/app/Support/Import/DynamicImportService.php):

1. leem CSV/XLSX usando OpenSpout;
2. identificam e validam os campos estruturais;
3. criam ou atualizam as definições de métricas configuradas pela empresa;
4. transformam as linhas em clientes, meses, valores de métricas e pesquisas;
5. executam o processo em transação e usam `upsert`, tornando a importação idempotente;
6. invalidam validações anteriores e chamam o recálculo da carteira.

### 7.3 Cálculo de risco

[RiskService.php](../app/app/Support/RiskService.php), chamado após a importação e pelo seeder de avaliações:

1. carrega cada cliente com definições, valores de métricas, períodos e pesquisas ordenados por mês;
2. para cancelados, ignora observações posteriores à data de cancelamento;
3. usa o último mês válido como `reference_month`;
4. monta a janela de observações e chama `MetricRisk::calcular()`;
5. compara o vetor do cliente com clientes cancelados elegíveis;
6. mantém até três similares, ordenados pela semelhança;
7. grava ou atualiza a avaliação `rules-v1` com `updateOrCreate`.

Comandos usuais:

```powershell
cd C:\dev\Inova\app
php artisan migrate
php artisan db:seed
```

Para uma carga limpa em ambiente local:

```powershell
php artisan migrate:fresh --seed
```

A chave da aplicação é gerada com:

```powershell
php artisan key:generate
```

O PHP precisa ter `pdo_sqlite` habilitado quando o banco configurado for SQLite.

## 8. Cálculo de risco

Implementação: [MetricRisk.php](../app/app/Support/Metricas/MetricRisk.php), [Risco.php](../app/app/Support/Risco.php) e [RiskService.php](../app/app/Support/RiskService.php).

### 8.1 Janela e agregação

O cálculo usa o último mês com dados como referência e considera a janela de até três meses do calendário que termina nesse mês. Para clientes cancelados, só entram dados anteriores à data de cancelamento.

- Métricas numéricas usam a mediana dos valores disponíveis na janela.
- Valores ausentes não são convertidos em alerta e não entram na agregação.
- Cada métrica ativa possui tipo, direção, valor saudável, valor crítico e peso em `MetricDefinition`.
- Métricas de texto e data são armazenadas para consulta, mas ficam fora da atenção.
- Indicadores derivados, como proporções, somas ou tendências, são calculados antes da comparação quando a regra de importação ou configuração do indicador exigir.
- A avaliação só é criada quando existe dado suficiente para pelo menos uma métrica na janela.

### 8.2 Intensidade, direção e pesos

Para cada métrica numérica ativa, a intensidade é calculada com os limites definidos pela empresa:

```text
intensidade = clamp(
    (mediana - valor_saudável) /
    (valor_crítico - valor_saudável)
)
clamp(x) = max(0, min(1, x))
```

A direção é representada pela ordem dos limites: em uma métrica `higher`, o valor crítico é maior que o saudável; em uma métrica `lower`, o valor crítico é menor que o saudável. Assim, a mesma fórmula funciona para indicadores em que aumentar é pior ou em que diminuir é pior.

A pontuação usa todas as métricas ativas com observação na janela e normaliza os pesos:

```text
parcela_métrica = arredondar(
    intensidade × peso / soma_dos_pesos_ativos × 100,
    1 casa
)
atenção = arredondar(soma das parcelas das métricas)
```

Uma métrica sem observação não recebe pontos. A normalização mantém a atenção entre 0 e 100 independentemente da soma dos pesos configurados. A configuração e os valores são persistidos em `metric_definitions` e `metric_values`; o resultado agregado fica em `risk_assessments`.

### 8.3 Evidências e explicabilidade

As métricas com parcela relevante são apresentadas como evidências, com intensidade, pontos, peso, valor observado, referência saudável e ação sugerida. A lista é ordenada pelos pontos calculados, mas todas as métricas ativas continuam compondo a atenção.

Quando há histórico de clientes cancelados, o sistema compara as intensidades das métricas compartilhadas e apresenta até três perfis semelhantes. Essa comparação é exploratória: não é probabilidade de cancelamento, não prova causalidade e não estima o momento da saída.

### 8.4 Níveis

| Atenção | Nível |
|---:|---|
| 0 a 24 | Baixo |
| 25 a 39 | Médio |
| 40 a 54 | Alto |
| 55 a 100 | Crítico |

Um sinal aparece na interface quando seus pontos atingem pelo menos 35% do peso máximo. Os sinais exibidos são ordenados do maior para o menor número de pontos.

### 8.5 Exposição e prioridade

A exposição mensal indicativa, persistida em `exposure_indicator` (antes `priority_score`), é:

```text
exposição_mensal = atenção / 100 x valor_mensal_do_contrato
```

Isso não é uma perda esperada estatística: é um indicador de tamanho do que está em jogo.

A **ordem da fila** não usa esse campo. Ela é calculada na consulta, em um só lugar (`Customer::RANKING_SQL` e `Customer::ranking()`), porque depende do `K` configurável da empresa e não pode ficar defasada:

```text
1º grupo: clientes ativos em alerta (atenção >= corte Médio); 2º grupo: os demais ativos; cancelados por último
dentro de cada grupo: atenção x (atenção + K) x valor_mensal_do_contrato, do maior para o menor
```

Assim, um contrato grande desempata entre clientes que já pedem contato, mas nenhum cliente sem alerta passa na frente de um em alerta.

O **prazo de contato** exibido na lista e na fila também sai da posição, e não só do nível: com a capacidade de `Risco::CONTATOS_POR_DIA` (3) contatos por dia, as posições 1 a 3 são "Contato hoje", 4 a 9 "Contato em até 3 dias" e 10 a 15 "Contato esta semana"; quem está abaixo do corte de alerta (nível Baixo) ou além da posição 15 segue o "acompanhamento normal".

### 8.6 Similaridade

A similaridade compara os vetores de severidade de dois clientes por distância euclidiana:

```text
d = sqrt(soma((severidade_a - severidade_b)^2))
semelhança = arredondar(100 x (1 - d / sqrt(8)))
```

O resultado é apresentado como percentual e serve para encontrar até três clientes cancelados com perfil parecido. Essa comparação é exploratória e não prova causalidade ou capacidade preditiva.

## 9. Dashboard e telas

### 9.1 Dashboard

Widgets registrados em [app/Filament/Widgets](../app/app/Filament/Widgets):

- **KPIs** ([KpisWidget.php](../app/app/Filament/Widgets/KpisWidget.php))
  - clientes ativos;
  - receita mensal ativa;
  - quantidade de ativos com atenção ≥ 40 (corte fixo);
  - receita já perdida, calculada a partir do valor mensal dos cancelados.
- **Clientes ativos por nível** ([NiveisChart.php](../app/app/Filament/Widgets/NiveisChart.php))
  - gráfico doughnut com Baixo, Médio, Alto e Crítico.
- **Risco médio por segmento** ([SegmentosChart.php](../app/app/Filament/Widgets/SegmentosChart.php))
  - média da atenção dos ativos agrupada por segmento.
- **Uso x SLA** ([TendenciaChart.php](../app/app/Filament/Widgets/TendenciaChart.php))
  - médias mensais da carteira ativa, em percentual.
- **Comparação exploratória** ([BacktestWidget.php](../app/app/Filament/Widgets/BacktestWidget.php))
  - atenção média de cancelados versus ativos;
  - quantidade de cancelados e ativos com atenção maior ou igual a 40.
- **Fila de atendimento** ([FilaTable.php](../app/app/Filament/Widgets/FilaTable.php))
  - oito ativos com maior exposição mensal;
  - link para o detalhe da empresa;
  - nível, atenção, contrato e principal sinal.

A comparação do backtest é explicitamente exploratória e não valida previsão de churn.

### 9.2 Lista de empresas

Implementação: [EmpresaResource.php](../app/app/Filament/Resources/Empresas/EmpresaResource.php).

Características:

- somente leitura; criação desabilitada;
- grid responsivo com 12, 24, 48 ou todos os registros por página;
- busca por código, segmento e campos exibidos;
- filtros por nível e segmento;
- ativos aparecem antes dos cancelados;
- dentro da ordenação, maior exposição aparece primeiro;
- cancelados ficam visualmente atenuados;
- o card apresenta nome, código, segmento, porte, nível, atenção, valor e principal sinal.

### 9.3 Detalhe de empresa

A página de detalhe mostra:

- nível e atenção de sinais;
- contrato mensal e exposição mensal indicativa;
- plano e SLA contratado;
- evidências dos últimos três meses;
- recomendação operacional para cada sinal;
- até três empresas canceladas com perfil similar;
- histórico de NPS;
- histórico mensal de chamados, reaberturas, SLA, uso, reclamações, atraso e reuniões.

### 9.4 Assistente

A página Filament está em [Assistente.php](../app/app/Filament/Pages/Assistente.php). O componente Livewire está em [AssistenteChat.php](../app/app/Livewire/AssistenteChat.php), com template em [assistente-chat.blade.php](../app/resources/views/livewire/assistente-chat.blade.php).

O componente:

- mantém mensagens no estado da sessão do componente;
- permite foco na carteira ou em uma empresa;
- não persiste conversas;
- limita a pergunta a 500 caracteres;
- responde com um LLM: Ollama local por padrão, ou uma API compatível com OpenAI para contextos maiores (`config/llm.php`), com o contexto da empresa ou do cliente em foco;
- barra perguntas fora do assunto e tentativas de mudar as regras ([Escopo.php](../app/app/Support/Llm/Escopo.php)): um filtro antes da chamada e uma instrução no prompt, que faz o modelo responder `FORA_DO_ESCOPO`;
- se o LLM estiver indisponível, cai nas respostas por regras.

As respostas por regras estão em [Assistente.php](../app/app/Support/Assistente.php). Elas normalizam caixa e acentos, identificam códigos no formato `C###` e usam palavras-chave.

Perguntas sobre a carteira cobrem:

- resumo e quantidade de ativos por nível;
- quem deve ser contatado primeiro;
- receita e exposição;
- risco por segmento;
- cancelamentos.

Perguntas sobre uma empresa cobrem:

- evidências da atenção;
- ações recomendadas;
- empresas canceladas similares;
- histórico de NPS;
- contrato, plano, SLA e exposição.

Quando a intenção não é reconhecida, o assistente devolve uma mensagem de orientação com exemplos.

### 9.5 Acessibilidade e recursos externos

[vlibras.blade.php](../app/resources/views/vlibras.blade.php) carrega o VLibras por script externo. A fonte visual configurada para o painel é Plus Jakarta Sans. A dependência externa do VLibras não possui fallback local implementado.

## 10. Seeders, factories e credenciais de desenvolvimento

[DatabaseSeeder.php](../app/database/seeders/DatabaseSeeder.php) executa a carga de usuários, dados de clientes e avaliações de risco.

[UserSeeder.php](../app/database/seeders/UserSeeder.php) cria os usuários demonstrativos:

- `admin@inova.com`
- `demo@inova.com`

Ambos usam a senha fixa `senha12345senha` no ambiente de demonstração. Essas credenciais devem ser substituídas ou removidas antes de qualquer ambiente real.

As factories em [database/factories](../app/database/factories) existem para testes e geração de dados, mas não necessariamente reproduzem a distribuição e a integridade da planilha oficial.

## 11. Testes

Os testes de feature estão em [tests/Feature](../app/tests/Feature), e os unitários em [tests/Unit](../app/tests/Unit).

A suíte de carteira cobre, entre outros pontos:

- redirecionamento de usuário não autenticado;
- renderização do painel;
- importação de dados;
- quantidades esperadas de clientes, métricas, NPS e avaliações;
- idempotência dos seeders;
- ordenação por exposição;
- renderização da lista e do detalhe;
- respostas do assistente;
- componente Livewire e modal de empresa.

Os números esperados na base de teste incluem:

- 80 clientes;
- 22 cancelados;
- 1.295 métricas;
- 422 respostas de NPS;
- 84 pesquisas sem resposta;
- 80 avaliações de risco;
- 23 métricas sem SLA.

Ainda não há cobertura unitária específica para todos os limites de [Risco.php](../app/app/Support/Risco.php), dados vazios, métricas incompletas, similaridade, severidades ou mensagens não reconhecidas do assistente.

Comando recomendado:

```powershell
cd C:\dev\Inova\app
php artisan test
```

## 12. Configuração e operação local

Fluxo básico:

```powershell
cd C:\dev\Inova\app
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan serve
```

O script `composer setup`, definido em [composer.json](../app/composer.json), automatiza parte desse processo. As variáveis de ambiente de banco estão em `.env` e os defaults estão em [config/database.php](../app/config/database.php).

Serviços previstos na configuração, mas não usados diretamente pelo domínio atual:

- Postmark, Resend, Amazon SES e SMTP;
- Slack;
- S3;
- Redis.

## 13. Limitações e riscos conhecidos

- O cálculo atual é um índice de atenção por regras, não uma probabilidade de churn.
- `risk_probability`, `expected_revenue_at_risk`, `confidence` e `recommended_action_json` não são preenchidos pelo seeder atual.
- Indicadores numéricos importados podem ser ativados como métricas da atenção quando houver dados e configuração válida. `tickets_critical`, `avg_resolution_hours` e `tickets_opened` são exemplos de indicadores que podem ser usados; `tickets_within_sla` permanece disponível para histórico quando não for configurado para pontuar.
- Não existe recálculo agendado: o risco é recalculado ao importar dados novos e ao alterar pesos ou limiares (`RiskService::recalcular`).
- Não há CRUD nem edição manual de clientes e não há histórico de importações. A planilha é enviada pela interface (tela Planilha e Configurações › Acrescentar novos meses).
- Não há API pública, integração com CRM nem e-mail de operação. Há notificações no painel (mudança de nível, reaproximação) e relatórios em PDF.
- O assistente usa LLM (Ollama local ou API externa), com fallback por regras; a conversa não é persistida e o modelo pode errar. A barreira de escopo é um filtro mais uma instrução ao modelo, e não é infalível.
- Todos os usuários autenticados têm acesso integral ao painel da própria empresa; não há papéis nem permissões.
- Existem credenciais demonstrativas fixas no seeder.
- A lista de clientes é somente leitura.
- Os dados do dashboard dependem da existência de avaliações `rules-v1`.
- A comparação entre ativos e cancelados é exploratória; não deve ser interpretada como validação estatística.
- A janela de NPS é de 3 meses do calendário (até o mês de referência); sem pesquisa nesse período o sinal de NPS fica neutro. O histórico exibido e o cálculo param no mês anterior à saída de clientes cancelados.
- A validação em período separado (calibra até o fim do ano anterior ao último cancelamento e testa nos seguintes) usa poucos cancelamentos; os números são indicativos. O "alerta em quem ficou" não é erro certo: um cliente ativo ainda pode cancelar depois.

## 14. Referências principais

- [composer.json](../app/composer.json)
- [AppPanelProvider.php](../app/app/Providers/Filament/AppPanelProvider.php)
- [Customer.php](../app/app/Models/Customer.php)
- [Risco.php](../app/app/Support/Risco.php)
- [Assistente.php](../app/app/Support/Assistente.php)
- [CustomerDataSeeder.php](../app/database/seeders/CustomerDataSeeder.php)
- [RiskAssessmentSeeder.php](../app/database/seeders/RiskAssessmentSeeder.php)
- [EmpresaResource.php](../app/app/Filament/Resources/Empresas/EmpresaResource.php)
- [KpisWidget.php](../app/app/Filament/Widgets/KpisWidget.php)
- [tests/Feature/CarteiraTest.php](../app/tests/Feature/CarteiraTest.php)
