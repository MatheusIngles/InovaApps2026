# InovaApps: arquitetura e funcionamento

> Documentação técnica e funcional baseada no estado atual do código-fonte. O documento descreve o que está implementado, incluindo as limitações conhecidas; não representa funcionalidades futuras.

## 1. Visão geral

O InovaApps é um painel interno para gestão de carteira de clientes, acompanhamento de saúde da conta e priorização de atendimento. A aplicação combina dados cadastrais, métricas mensais, pesquisas de satisfação e avaliações de risco calculadas por regras.

O sistema responde a três perguntas principais:

1. Quais clientes estão ativos e qual é o nível de risco de cada um?
2. Quais clientes devem receber atenção primeiro, considerando score e valor mensal?
3. Quais sinais explicam o score e qual ação operacional é recomendada?

O produto não é, no estado atual, um modelo estatístico de previsão de churn. O score é determinístico, baseado em regras explícitas, e a comparação com clientes cancelados é exploratória.

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

A configuração de dependências está em [composer.json](../composer.json) e [package.json](../package.json). O frontend usa os componentes do Filament, Livewire e Alpine; não há JavaScript próprio relevante em `resources/js/app.js`.

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
        +-- CustomerMetric
        +-- CustomerNps
        +-- RiskAssessment
        |
        v
SQLite / banco configurado pelo Laravel

Importação inicial:
XLSX -> CustomerDataSeeder -> customers, customer_metrics, customer_nps
                         -> RiskAssessmentSeeder -> risk_assessments
```

### 3.2 Bootstrap e painel

- [bootstrap/app.php](../bootstrap/app.php) registra o health check `/up`.
- [AppPanelProvider.php](../app/Providers/Filament/AppPanelProvider.php) configura o painel Filament.
- O painel usa caminho vazio, portanto atende a raiz `/`.
- O painel exige autenticação, sessão, CSRF, binding de rotas e autenticação de sessão.
- O painel permite login, cadastro e recuperação de senha.
- O nome visual do produto é `InovaApps 2026`.
- O tema atual é claro, com azul como cor primária e Slate como cor neutra.
- O hook `BODY_END` injeta o widget externo do VLibras.

[routes/web.php](../routes/web.php) não declara rotas de negócio; as rotas são descobertas e registradas pelo Filament.

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

[User.php](../app/Models/User.php) permite acesso ao painel para qualquer usuário autenticado. Não há perfis, papéis, permissões por equipe ou isolamento por tenant.

## 4. Organização do código

- `app/Models`: modelos Eloquent e relações.
- `app/Support`: regras de risco e respostas do assistente.
- `app/Filament/Resources`: recursos CRUD/consulta do Filament.
- `app/Filament/Pages`: páginas customizadas do painel.
- `app/Filament/Widgets`: KPIs, gráficos e fila de atendimento.
- `app/Livewire`: estado e interação do chat.
- `database/migrations`: estrutura versionada do banco.
- `database/seeders`: importação e cálculo inicial dos dados.
- `database/data`: planilha de entrada.
- `resources/views`: templates Blade do assistente, modal e acessibilidade.
- `tests`: testes de feature e unitários.

O controller base em [Controller.php](../app/Http/Controllers/Controller.php) não contém lógica de domínio.

## 5. Modelo de domínio

### 5.1 Customer

Arquivo: [Customer.php](../app/Models/Customer.php)

Representa uma empresa/cliente da carteira.

Relações:

- `hasMany(CustomerMetric::class)` em `metrics`.
- `hasMany(CustomerNps::class)` em `npsResponses`.
- `hasMany(RiskAssessment::class)` em `riskAssessments`.
- `hasOne(RiskAssessment::class)` em `currentAssessment`, filtrado por `rules-v1` e selecionando o mês mais recente.

Comportamentos importantes:

- A chave de rota é `external_code`, por exemplo `C012`.
- `dashboard()` junta o cliente à avaliação mais recente da versão `rules-v1`.
- `ativas()` retorna somente clientes com status `Ativo`.
- `ordenar()` coloca ativos antes de cancelados e ordena pela exposição decrescente.
- `score` vem de `health_score`.
- `exposicao` vem de `priority_score`.
- `nivel` é derivado pelos limiares de [Risco.php](../app/Support/Risco.php).
- `sinais` e `similares` são lidos de `signals_json`.
- `hist` e `nps` transformam os dados persistidos em estruturas próprias para a tela.

A função `brl()` exibe valores como moeda brasileira sem casas decimais, por exemplo `R$ 12.500`.

### 5.2 CustomerMetric

Arquivo: [CustomerMetric.php](../app/Models/CustomerMetric.php)

Representa uma fotografia mensal de atendimento, uso, pagamentos e reuniões de um cliente. O par `customer_id + reference_month` é único.

Campos principais:

- chamados abertos, críticos e reabertos;
- chamados dentro do SLA;
- percentual de SLA, que pode ser nulo quando não há chamados;
- tempo médio de resolução em horas;
- reclamações formais;
- percentual de uso da plataforma;
- dias de atraso de pagamento;
- reuniões previstas e realizadas.

### 5.3 CustomerNps

Arquivo: [CustomerNps.php](../app/Models/CustomerNps.php)

Representa uma pesquisa mensal de satisfação. O par `customer_id + reference_month` é único.

Campos:

- `answered`: indica se houve resposta;
- `score`: nota, anulável quando não houve resposta;
- `classification`: classificação persistida, quando disponível.

Pesquisas sem resposta continuam registradas e influenciam a severidade de NPS como comportamento de silêncio.

### 5.4 RiskAssessment

Arquivo: [RiskAssessment.php](../app/Models/RiskAssessment.php)

Armazena o resultado calculado para um cliente, mês de referência e versão de modelo. O índice único é `customer_id + reference_month + model_version`.

Campos relevantes:

- `health_score`: score de regras entre 0 e 100;
- `priority_score`: exposição financeira mensal indicativa;
- `risk_probability`: reservado para probabilidade estatística; atualmente nulo;
- `expected_revenue_at_risk`: reservado para receita esperada em risco; atualmente nulo;
- `confidence`: reservado para confiança do modelo; atualmente nulo;
- `signals_json`: evidências, severidades e clientes similares;
- `recommended_action_json`: reservado; as ações atuais ficam dentro das evidências;
- `model_version`: atualmente `rules-v1`;
- `calculated_at`: data/hora do cálculo.

## 6. Banco de dados

Todas as foreign keys de dados de clientes usam `cascadeOnDelete`: ao remover um cliente, suas métricas, pesquisas e avaliações são removidas.

### 6.1 Tabela `customers`

Migration: [2026_09_19_132055_create_customers_table.php](../database/migrations/2026_09_19_132055_create_customers_table.php)

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

### 6.2 Tabela `customer_metrics`

Migration: [2026_09_19_132056_create_customer_metrics_table.php](../database/migrations/2026_09_19_132056_create_customer_metrics_table.php)

| Campo | Tipo | Regra/uso |
|---|---|---|
| `customer_id` | foreign key | cliente proprietário |
| `reference_month` | date | mês de referência |
| `tickets_opened` | unsigned smallint | chamados abertos |
| `tickets_critical` | unsigned smallint | chamados críticos |
| `tickets_reopened` | unsigned smallint | chamados reabertos |
| `tickets_within_sla` | unsigned smallint | chamados dentro do SLA |
| `sla_percentage` | decimal(5,2) nullable | percentual calculado/importado |
| `avg_resolution_hours` | decimal(8,2) | tempo médio de resolução |
| `formal_complaints` | unsigned smallint | reclamações formais |
| `platform_usage_percentage` | decimal(5,2) | uso da plataforma |
| `payment_delay_days` | unsigned smallint | atraso de pagamento |
| `meetings_expected` | unsigned smallint | reuniões previstas |
| `meetings_completed` | unsigned smallint | reuniões realizadas |
| `customer_id + reference_month` | unique | uma linha por cliente/mês |

A migration [2026_09_19_134626_make_customer_metrics_sla_nullable.php](../database/migrations/2026_09_19_134626_make_customer_metrics_sla_nullable.php) permite SLA nulo em meses sem chamados.

### 6.3 Tabela `customer_nps`

Migration: [2026_09_19_132057_create_customer_nps_table.php](../database/migrations/2026_09_19_132057_create_customer_nps_table.php)

| Campo | Tipo | Regra/uso |
|---|---|---|
| `customer_id` | foreign key | cliente proprietário |
| `reference_month` | date | mês de referência |
| `answered` | boolean | houve resposta? |
| `score` | unsigned tinyint nullable | nota quando respondido |
| `classification` | string nullable | classificação da pesquisa |
| `customer_id + reference_month` | unique | uma pesquisa por cliente/mês |

### 6.4 Tabela `risk_assessments`

Migration inicial: [2026_09_19_132059_create_risk_assessments_table.php](../database/migrations/2026_09_19_132059_create_risk_assessments_table.php).

A migration [2026_09_19_134141_add_rule_score_to_risk_assessments_table.php](../database/migrations/2026_09_19_134141_add_rule_score_to_risk_assessments_table.php) adiciona `health_score` e torna `risk_probability` e `expected_revenue_at_risk` anuláveis, refletindo a implementação atual baseada em regras.

| Campo | Tipo | Regra/uso atual |
|---|---|---|
| `customer_id` | foreign key | cliente avaliado |
| `reference_month` | date | último mês considerado |
| `health_score` | unsigned tinyint nullable | score efetivo, 0 a 100 |
| `risk_probability` | decimal(7,6) nullable | não calculado |
| `priority_score` | decimal(14,2) nullable | exposição indicativa |
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

A fonte principal é [INOVAAPPS_base_de_dados.xlsx](../database/data/INOVAAPPS_base_de_dados.xlsx). O importador espera as abas:

- `clientes`;
- `atendimento_mensal`;
- `pesquisas_nps`;
- `situacao_clientes`.

### 7.2 Importação

[CustomerDataSeeder.php](../database/seeders/CustomerDataSeeder.php):

1. lê o XLSX usando OpenSpout;
2. valida as abas esperadas;
3. transforma linhas da planilha em registros de clientes, métricas e NPS;
4. executa o processo em transação;
5. grava em lotes de 250 registros;
6. usa `upsert`, tornando a importação idempotente.

### 7.3 Cálculo de risco

[RiskAssessmentSeeder.php](../database/seeders/RiskAssessmentSeeder.php):

1. carrega cada cliente com métricas e NPS ordenados por mês;
2. para cancelados, ignora métricas posteriores à data de cancelamento;
3. usa o último mês válido como `reference_month`;
4. monta os vetores de histórico e chama `Risco::calcular()`;
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

Implementação: [Risco.php](../app/Support/Risco.php).

### 8.1 Janela e agregação

O cálculo considera os três meses mais recentes de métricas (`w`) e, quando existe histórico anterior, compara a janela com os meses anteriores (`prev`).

- Uso, SLA, atraso e outras medidas mensais usam mediana na janela recente.
- Chamados e reuniões são somados na janela.
- Reincidência é chamados reabertos divididos por chamados abertos.
- Execução de reuniões é reuniões realizadas divididas por reuniões previstas.
- NPS usa a última resposta disponível dentro dos três meses.
- Se não houver pesquisa respondida, a nota fica ausente.
- Um mês sem chamados recebe SLA padrão de 100% na regra.
- Se não houver reuniões previstas, a execução fica ausente e sua severidade é zero.

### 8.2 Sinais e pesos

O score final é a soma de oito severidades entre 0 e 1, multiplicadas por pesos que totalizam 100:

| Sinal | Peso | Interpretação |
|---|---:|---|
| Uso da plataforma | 20 | uso abaixo do patamar esperado |
| SLA cumprido | 15 | queda no cumprimento do SLA |
| Reincidência de chamados | 10 | proporção de chamados reabertos |
| Reclamações formais | 10 | volume de reclamações em três meses |
| Atraso de pagamento | 10 | dias de atraso |
| Reuniões realizadas | 12 | baixa execução da cadência prevista |
| NPS | 15 | nota baixa e/ou ausência de resposta |
| Tendência de queda | 8 | queda de uso em relação ao início do histórico |

A pontuação de cada sinal é:

```text
pontos_sinal = arredondar(severidade_sinal x peso_sinal, 1)
score = arredondar(soma dos pontos_sinal)
```

A severidade é limitada ao intervalo `[0, 1]` pela função de saturação. Portanto, cada sinal não ultrapassa o próprio peso.

### 8.3 Fórmulas de severidade

Com `clamp(x) = max(0, min(1, x))`:

```text
uso     = clamp((85 - mediana(uso_pct)) / 35)
sla     = clamp((85 - mediana(sla_pct)) / 45)
reinc   = clamp(reabertos / abertos / 0,25)
recl    = clamp(reclamacoes / 4)
atraso  = clamp(mediana(dias_atraso) / 10)
reun    = clamp((0,8 - realizadas/previstas) / 0,6)
```

Para NPS:

```text
nota_nps = 0                         se não há nota
nota_nps = clamp((8 - nota) / 6)     quando há nota
silencio = pesquisas_sem_resposta / total_de_pesquisas
nps      = 0,5 x nota_nps + 0,5 x silencio
```

Para tendência:

```text
queda_uso = média_do_historico_anterior - mediana_do_uso_recente
tend      = clamp(queda_uso / 30)
```

A implementação usa números decimais com ponto no código PHP; a vírgula acima é apenas notação brasileira.

### 8.4 Níveis

| Score | Nível |
|---:|---|
| 0 a 24 | Baixo |
| 25 a 39 | Médio |
| 40 a 54 | Alto |
| 55 a 100 | Crítico |

Um sinal aparece na interface quando seus pontos atingem pelo menos 35% do peso máximo. Os sinais exibidos são ordenados do maior para o menor número de pontos.

### 8.5 Exposição e prioridade

A exposição mensal indicativa, persistida em `priority_score`, é:

```text
exposição_mensal = score / 100 x valor_mensal_do_contrato
```

Isso não é uma perda esperada estatística. É uma aproximação operacional que combina intensidade de sinais com valor do contrato para ordenar a fila de atendimento.

### 8.6 Similaridade

A similaridade compara os vetores de severidade de dois clientes por distância euclidiana:

```text
d = sqrt(soma((severidade_a - severidade_b)^2))
semelhança = arredondar(100 x (1 - d / sqrt(8)))
```

O resultado é apresentado como percentual e serve para encontrar até três clientes cancelados com perfil parecido. Essa comparação é exploratória e não prova causalidade ou capacidade preditiva.

## 9. Dashboard e telas

### 9.1 Dashboard

Widgets registrados em [app/Filament/Widgets](../app/Filament/Widgets):

- **KPIs** ([KpisWidget.php](../app/Filament/Widgets/KpisWidget.php))
  - clientes ativos;
  - receita mensal ativa;
  - quantidade de ativos com score alto ou crítico;
  - receita já perdida, calculada a partir do valor mensal dos cancelados.
- **Clientes ativos por nível** ([NiveisChart.php](../app/Filament/Widgets/NiveisChart.php))
  - gráfico doughnut com Baixo, Médio, Alto e Crítico.
- **Risco médio por segmento** ([SegmentosChart.php](../app/Filament/Widgets/SegmentosChart.php))
  - média do score dos ativos agrupada por segmento.
- **Uso x SLA** ([TendenciaChart.php](../app/Filament/Widgets/TendenciaChart.php))
  - médias mensais da carteira ativa, em percentual.
- **Comparação exploratória** ([BacktestWidget.php](../app/Filament/Widgets/BacktestWidget.php))
  - score médio de cancelados versus ativos;
  - quantidade de cancelados e ativos com score maior ou igual a 40.
- **Fila de atendimento** ([FilaTable.php](../app/Filament/Widgets/FilaTable.php))
  - oito ativos com maior exposição mensal;
  - link para o detalhe da empresa;
  - nível, score, contrato e principal sinal.

A comparação do backtest é explicitamente exploratória e não valida previsão de churn.

### 9.2 Lista de empresas

Implementação: [EmpresaResource.php](../app/Filament/Resources/Empresas/EmpresaResource.php).

Características:

- somente leitura; criação desabilitada;
- grid responsivo com 12, 24, 48 ou todos os registros por página;
- busca por código, segmento e campos exibidos;
- filtros por nível e segmento;
- ativos aparecem antes dos cancelados;
- dentro da ordenação, maior exposição aparece primeiro;
- cancelados ficam visualmente atenuados;
- o card apresenta nome, código, segmento, porte, nível, score, valor e principal sinal.

### 9.3 Detalhe de empresa

A página de detalhe mostra:

- nível e score de sinais;
- contrato mensal e exposição mensal indicativa;
- plano e SLA contratado;
- evidências dos últimos três meses;
- recomendação operacional para cada sinal;
- até três empresas canceladas com perfil similar;
- histórico de NPS;
- histórico mensal de chamados, reaberturas, SLA, uso, reclamações, atraso e reuniões.

### 9.4 Assistente

A página Filament está em [Assistente.php](../app/Filament/Pages/Assistente.php). O componente Livewire está em [AssistenteChat.php](../app/Livewire/AssistenteChat.php), com template em [assistente-chat.blade.php](../resources/views/livewire/assistente-chat.blade.php).

O componente:

- mantém mensagens no estado da sessão do componente;
- limita a pergunta a 300 caracteres;
- permite foco na carteira ou em uma empresa;
- não persiste conversas;
- não chama LLM ou API externa.

A lógica de resposta está em [Assistente.php](../app/Support/Assistente.php). Ela normaliza caixa e acentos, identifica códigos no formato `C###` e usa palavras-chave.

Perguntas sobre a carteira cobrem:

- resumo e quantidade de ativos por nível;
- quem deve ser contatado primeiro;
- receita e exposição;
- risco por segmento;
- cancelamentos.

Perguntas sobre uma empresa cobrem:

- evidências do score;
- ações recomendadas;
- empresas canceladas similares;
- histórico de NPS;
- contrato, plano, SLA e exposição.

Quando a intenção não é reconhecida, o assistente devolve uma mensagem de orientação com exemplos.

### 9.5 Acessibilidade e recursos externos

[vlibras.blade.php](../resources/views/vlibras.blade.php) carrega o VLibras por script externo. A fonte visual configurada para o painel é Plus Jakarta Sans. A dependência externa do VLibras não possui fallback local implementado.

## 10. Seeders, factories e credenciais de desenvolvimento

[DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php) executa a carga de usuários, dados de clientes e avaliações de risco.

[UserSeeder.php](../database/seeders/UserSeeder.php) cria os usuários demonstrativos:

- `admin@inova.com`
- `demo@inova.com`

Ambos usam a senha fixa `senha123` no ambiente de demonstração. Essas credenciais devem ser substituídas ou removidas antes de qualquer ambiente real.

As factories em [database/factories](../database/factories) existem para testes e geração de dados, mas não necessariamente reproduzem a distribuição e a integridade da planilha oficial.

## 11. Testes

Os testes de feature estão em [tests/Feature](../tests/Feature), e os unitários em [tests/Unit](../tests/Unit).

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

Ainda não há cobertura unitária específica para todos os limites de [Risco.php](../app/Support/Risco.php), dados vazios, métricas incompletas, similaridade, severidades ou mensagens não reconhecidas do assistente.

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

O script `composer setup`, definido em [composer.json](../composer.json), automatiza parte desse processo. As variáveis de ambiente de banco estão em `.env` e os defaults estão em [config/database.php](../config/database.php).

Serviços previstos na configuração, mas não usados diretamente pelo domínio atual:

- Postmark, Resend, Amazon SES e SMTP;
- Slack;
- S3;
- Redis.

## 13. Limitações e riscos conhecidos

- O cálculo atual é um score de regras, não uma probabilidade de churn.
- `risk_probability`, `expected_revenue_at_risk`, `confidence` e `recommended_action_json` não são preenchidos pelo seeder atual.
- `tickets_critical`, `tickets_within_sla` e `avg_resolution_hours` são armazenados, mas não entram no score atual.
- Não existe recálculo agendado; o cálculo acontece no seeding.
- Não há CRUD, edição manual, upload de planilha pela interface ou histórico de importações.
- Não há API pública, integração com CRM, notificações, exportação ou e-mail de operação.
- O assistente é baseado em palavras-chave e não mantém histórico persistente.
- Todos os usuários autenticados têm acesso integral ao painel.
- Existem credenciais demonstrativas fixas no seeder.
- A lista de empresas é somente leitura.
- Os dados do dashboard dependem da existência de avaliações `rules-v1`.
- A comparação entre ativos e cancelados é exploratória; não deve ser interpretada como validação estatística.
- O README principal ainda é o README padrão do Laravel; este documento é a referência atual da arquitetura e do comportamento funcional.

## 14. Referências principais

- [composer.json](../composer.json)
- [AppPanelProvider.php](../app/Providers/Filament/AppPanelProvider.php)
- [Customer.php](../app/Models/Customer.php)
- [Risco.php](../app/Support/Risco.php)
- [Assistente.php](../app/Support/Assistente.php)
- [CustomerDataSeeder.php](../database/seeders/CustomerDataSeeder.php)
- [RiskAssessmentSeeder.php](../database/seeders/RiskAssessmentSeeder.php)
- [EmpresaResource.php](../app/Filament/Resources/Empresas/EmpresaResource.php)
- [KpisWidget.php](../app/Filament/Widgets/KpisWidget.php)
- [tests/Feature/CarteiraTest.php](../tests/Feature/CarteiraTest.php)
