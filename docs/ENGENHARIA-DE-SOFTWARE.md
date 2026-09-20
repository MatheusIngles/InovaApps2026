# Engenharia de software do Seer

Diagramas e decisões de projeto. Os diagramas usam [Mermaid](https://mermaid.js.org/), que o GitHub desenha direto no navegador. Os nomes seguem o código (`app/Models`, `app/Support`).

## 1. Requisitos

**Funcionais principais**

| Código | Requisito |
|---|---|
| RF01 | Cada empresa entra no seu próprio contexto, sem ver dados de outra. |
| RF02 | Importar planilhas (XLSX/CSV) com uma ou várias abas e mapear as colunas. |
| RF03 | Definir métricas próprias (tipo, faixa saudável/crítica, peso, direção). |
| RF04 | Calcular a atenção (0 a 100) de cada cliente e o nível de alerta. |
| RF05 | Ordenar a fila de atendimento por atenção e valor do contrato. |
| RF06 | Explicar por que um cliente está em alerta e sugerir a ação. |
| RF07 | Comparar com clientes que cancelaram (backtest) e gerar o relatório de evidências em PDF. |
| RF08 | Conversar com um assistente de IA, por texto, voz e Libras. |
| RF09 | A empresa pode desligar a IA e continuar usando o sistema. |
| RF10 | Notificar quando um cliente sobe de nível. |

**Não funcionais:** isolamento entre empresas, cálculo determinístico e explicável, acessibilidade (VLibras, texto ajustável, voz), custo de IA controlado, segurança dos dados (veja [IA: custo e segurança](IA-CUSTO-E-SEGURANCA.md)).

## 2. Casos de uso

```mermaid
flowchart LR
    G([Gestor da empresa])
    A([Administrador da empresa])
    N([Novo visitante])
    IA([Provedor de IA<br/>NVIDIA / Ollama])

    subgraph Seer
        UC0(Cadastrar empresa e usuário)
        UC1(Entrar no sistema)
        UC2(Importar planilha)
        UC3(Configurar métricas, pesos e níveis)
        UC4(Ver painel e gráficos)
        UC5(Consultar a fila de atendimento)
        UC6(Ver detalhes e motivos de um cliente)
        UC7(Marcar cliente como resolvido)
        UC8(Conversar com o assistente)
        UC9(Gerar relatório em PDF)
        UC10(Ligar ou desligar a IA)
        UC11(Personalizar tema e logo)
        UC12(Receber notificações)
    end

    N --> UC0
    G --> UC1
    G --> UC4
    G --> UC5
    G --> UC6
    G --> UC7
    G --> UC8
    G --> UC9
    G --> UC12
    A --> UC2
    A --> UC3
    A --> UC10
    A --> UC11
    A --> UC1
    UC8 -.usa quando ligada.-> IA
    UC9 -.usa quando ligada.-> IA
    UC2 -.recalcula.-> UC5
    UC3 -.recalcula.-> UC5
```

Tanto o gestor quanto o administrador são usuários da mesma empresa; o sistema hoje não separa papéis por permissão, o desenho acima mostra quem costuma fazer cada coisa.

### Descrição dos casos principais

**UC2 Importar planilha**
1. O usuário baixa o modelo (Leia-me, dicionário e abas de dados) ou usa a própria planilha.
2. Envia o arquivo; o sistema lê todas as abas e junta pelo `cliente_id`.
3. O sistema propõe o tipo de cada coluna nova (a partir do dicionário, se existir).
4. O usuário confirma quais colunas viram métricas e ajusta faixas e pesos.
5. O sistema grava os dados, ativa os sinais extras que tiverem dados e recalcula a atenção de todos os clientes.
6. Arquivos grandes (> 2 MB) rodam em fila.

Exceção: colunas duplicadas, sem `cliente_id` ou com valores fora do formato são recusadas com a mensagem do erro.

**UC5 Consultar a fila:** o sistema lista primeiro quem está em alerta (Médio ou acima) e, dentro de cada grupo, ordena por atenção × (atenção + K) × valor do contrato. Cada cliente mostra o prazo de contato pela posição.

**UC8 Conversar com o assistente:** o sistema monta o contexto só da empresa logada, chama a IA (NVIDIA, depois Ollama) e, se a IA estiver desligada, falhar ou responder mal, responde por regras. Perguntas fora do escopo ou tentativas de mudar o papel da IA são recusadas.

**UC10 Ligar ou desligar a IA:** em Configurações › Assistente de IA. Desligada, nenhuma chamada externa acontece.

## 3. Diagrama de classes (domínio)

```mermaid
classDiagram
    class Company {
        +string name
        +string slug
        +array theme
        +array metric_weights
        +array level_thresholds
        +int priority_balance
        +array chat_settings
        +datetime imported_at
        +pesos() array
        +limiares() array
        +prioridadeK() int
        +chat() array
        +hasLegacyMetrics() bool
    }
    class User {
        +string name
        +string email
        +string password
    }
    class Customer {
        +string external_code
        +string segment
        +string size
        +string plan
        +decimal monthly_value
        +string status
        +date cancelled_at
        +date resolved_at
        +ordenar() Builder
    }
    class CustomerMetric {
        +date reference_month
        +int tickets_opened
        +int tickets_critical
        +int tickets_reopened
        +float sla_percentage
        +float avg_resolution_hours
        +int formal_complaints
        +float platform_usage_percentage
        +int payment_delay_days
        +int meetings_expected
        +int meetings_completed
    }
    class CustomerNps {
        +date reference_month
        +bool answered
        +int score
    }
    class CustomerPeriod {
        +date reference_month
        +decimal monthly_value
    }
    class MetricDefinition {
        +string code
        +string label
        +string value_type
        +string direction
        +float healthy_value
        +float critical_value
        +float weight
        +bool enabled
        +semScore(tipo) bool
    }
    class MetricValue {
        +date reference_month
        +float value
        +string text_value
    }
    class RiskAssessment {
        +date reference_month
        +int health_score
        +decimal exposure_indicator
        +json signals_json
        +string model_version
    }

    Company "1" --> "*" User
    Company "1" --> "*" Customer
    Company "1" --> "*" MetricDefinition
    Customer "1" --> "*" CustomerMetric
    Customer "1" --> "*" CustomerNps
    Customer "1" --> "*" CustomerPeriod
    Customer "1" --> "*" MetricValue
    Customer "1" --> "*" RiskAssessment
    MetricDefinition "1" --> "*" MetricValue
```

`health_score` é o nome da coluna que guarda a **atenção** (0 a 100). O nome vem da primeira versão do projeto.

### Serviços (camada de regra de negócio, `app/Support`)

```mermaid
classDiagram
    class Risco {
        +PESOS
        +LIMIARES
        +calcular() array
        +nivel(atencao) string
        +prazoPorPosicao() string
        +semelhanca() int
    }
    class MetricRisk {
        +calcular() array
    }
    class RiskService {
        +recalcular(Company) int
    }
    class DynamicImportService {
        +sugerir() array
        +importar() void
    }
    class PlanilhaReader {
        +lerModelo() array
    }
    class SinaisExtras {
        +ativar(Company) int
    }
    class Backtest {
        +resumo(Company) array
    }
    class Llm {
        +responder() array
    }
    class Contexto {
        +sistema(Customer) string
    }
    class Escopo {
        +tentaBurlar(texto) bool
    }
    class Assistente {
        +responder(texto) string
    }
    class NotificacaoService {
        +avaliar() void
    }
    class RelatorioService

    RiskService ..> MetricRisk
    MetricRisk ..> Risco
    RiskService ..> NotificacaoService
    DynamicImportService ..> PlanilhaReader
    DynamicImportService ..> SinaisExtras
    DynamicImportService ..> RiskService
    Backtest ..> Risco
    RelatorioService ..> Backtest
    RelatorioService ..> Llm
    Llm ..> Contexto
    Contexto ..> Escopo
    Llm ..> Assistente : reserva por regras
```

## 4. Fluxo de dados

```mermaid
sequenceDiagram
    actor U as Usuário
    participant I as ImportarPlanilha
    participant D as DynamicImportService
    participant R as RiskService
    participant B as Banco (SQLite)
    U->>I: envia a planilha
    I->>D: lê abas, junta por cliente_id, propõe métricas
    U->>I: confirma métricas, faixas e pesos
    I->>D: importar()
    D->>B: grava clientes, métricas e valores
    D->>R: recalcular(empresa)
    R->>B: lê histórico dos últimos 3 meses
    R->>R: calcula atenção, sinais e clientes parecidos com cancelados
    R->>B: grava RiskAssessment
    U->>B: abre Painel / Clientes (fila ordenada)
```

```mermaid
sequenceDiagram
    actor U as Usuário
    participant C as AssistenteChat
    participant E as Escopo
    participant L as Llm
    participant A as Assistente (regras)
    U->>C: pergunta
    C->>E: tentaBurlar?
    alt fora do escopo
        E-->>C: recusa fixa
    else IA ligada na empresa
        C->>L: contexto da empresa + pergunta
        L->>L: 1) API NVIDIA  2) Ollama local
        alt resposta boa
            L-->>C: texto
        else falha ou resposta fraca
            C->>A: responder por regras
        end
    else IA desligada
        C->>A: responder por regras
    end
    C-->>U: resposta (texto, voz e/ou Libras)
```

## 5. Rotas principais

Todas passam pelo middleware `SetCompanyContext`, que define a empresa a partir do **usuário logado** (nunca de dados da requisição) e encerra a sessão se ela estiver ligada a outra empresa. O middleware `CabecalhosDeSeguranca` vale para todas as respostas.

### Mapa das rotas

```mermaid
flowchart TD
    V([Visitante]) --> RAIZ["GET /"]
    RAIZ -->|sem login| LOGIN["GET /login<br/>POST Livewire: entrar"]
    V --> REG["GET /register<br/>cadastro de empresa"]
    REG --> PLAN
    LOGIN -->|10 erros em 15 min: bloqueio| LOGIN
    LOGIN --> OK{"empresa tem dados?"}
    OK -->|não| PLAN["GET /planilha<br/>importar"]
    OK -->|sim| CLI["GET /empresas<br/>lista e fila"]

    subgraph PF["Painel Filament (exige login)"]
        CLI --> DET["GET /empresas/{id}<br/>detalhe do cliente"]
        CLI --> PNL["GET /painel<br/>abas: Visão geral, Gráficos, Segmentos"]
        CLI --> ASS["GET /assistente<br/>chat"]
        CLI --> CFG["GET /configuracoes<br/>métricas, níveis, fila, IA, tema"]
        PLAN
    end

    subgraph AX["Auxiliares (exigem login)"]
        MOD["GET /modelo/planilha.xlsx<br/>GET /modelo/planilha (CSV)"]
        REL["GET /relatorios/{uuid}/baixar<br/>PDF gerado após o pedido"]
        VOZ["POST /assistente/voz<br/>áudio neural, 20 por minuto"]
    end

    PLAN -.baixa o modelo.-> MOD
    DET -.gera relatório.-> REL
    ASS -.modo conversa.-> VOZ

    subgraph IN["Infra"]
        UP["GET /up<br/>saúde"]
        LW["POST /livewire-*/update<br/>ações dos componentes"]
        ST["GET /storage/...<br/>logos enviados"]
    end
```

### Regras de acesso por rota

| Rota | Login | Observação |
|---|---|---|
| `/` | não | Redireciona: sem login para `/login`; com login para a planilha (empresa sem dados) ou a lista de clientes |
| `/login`, `/register` | não | Login com bloqueio por e-mail; o cadastro cria empresa e primeiro usuário |
| `/painel`, `/empresas`, `/empresas/{id}`, `/planilha`, `/configuracoes`, `/assistente` | sim | Páginas do Filament; empresa sem dados só acessa `/planilha` |
| `/modelo/planilha`, `/modelo/planilha.xlsx` | sim | Modelo com os dados da empresa logada |
| `/relatorios/{arquivo}/baixar` | sim | O nome é um UUID e o arquivo fica na pasta da empresa e do usuário: não dá para baixar o de outro |
| `POST /assistente/voz` | sim | CSRF, máximo de 1.500 caracteres, 20 por minuto |
| `/up` | não | Só a página de saúde, sem versão nem configuração |
| `POST /livewire-*/update` | conforme o componente | Propriedades sensíveis são bloqueadas (`#[Locked]`) |

### Sequência: entrar no sistema

```mermaid
sequenceDiagram
    actor U as Usuário
    participant L as /login (Livewire)
    participant RL as Limitador (por e-mail)
    participant M as SetCompanyContext
    participant P as Painel
    U->>L: e-mail e senha
    L->>RL: já houve 10 erros em 15 min?
    alt bloqueado
        RL-->>U: aguarde alguns minutos
    else liberado
        L->>L: confere a senha
        L->>M: sessão iniciada
        M->>M: empresa = empresa do usuário
        M->>P: redireciona (planilha ou clientes)
    end
```

### Sequência: relatório em PDF

```mermaid
sequenceDiagram
    actor U as Usuário
    participant C as Componente RelatorioEmpresa
    participant J as GerarRelatorioEmpresaJob
    participant S as Storage (local)
    participant R as GET /relatorios/{uuid}/baixar
    U->>C: pede o relatório
    C->>J: dispara o job logo após a resposta (empresa, usuário, uuid)
    J->>J: monta os números; IA escreve o texto, ou regras se a IA estiver desligada
    J->>S: grava o PDF na pasta da empresa e do usuário
    J-->>U: notificação com o link do PDF
    U->>R: baixa
    R->>S: só encontra se for da empresa e do usuário logados
    R-->>U: relatorio.pdf
```

## 6. Decisões de projeto

| Decisão | Por quê |
|---|---|
| Atenção calculada por regras, sem IA | É explicável, auditável, barata e igual toda vez. A IA só explica o resultado. |
| Mediana dos 3 últimos meses | Ignora piora de um mês só. |
| Métricas por empresa (`MetricDefinition`) | Cada empresa tem dados diferentes; o modelo padrão de 8 sinais é só um ponto de partida. |
| Multi-tenant por `company_id` com escopo global | Isolamento simples e testável; um único banco. |
| SQLite | Suficiente para o volume atual; o app aceita MySQL/PostgreSQL trocando o `.env`. |
| Fila `database` | Sem serviço extra; importações grandes e relatórios rodam em segundo plano. |
| IA em cascata com reserva por regras | O sistema nunca fica sem resposta e a IA é opcional. |
| Filament + Livewire | Painel administrativo completo com pouco código de front. |

## 7. Testes

A suíte PHPUnit cobre isolamento entre empresas, importação (inclusive várias abas e dicionário), cálculo da atenção, configurações, fila, relatório, notificações, segurança (login, cabeçalhos, proxy) e o assistente. Rodar: `composer test` (dentro de `app/`).
