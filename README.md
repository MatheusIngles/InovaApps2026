# InovaApps 2026

Plataforma **multi-tenant** de gestão de carteira de clientes, saúde da conta e priorização de atendimento, desenvolvida para o desafio **INOVAAPPS 2026** (enunciado em `Desafio - INOVAAPPS 2026.pdf`, base de dados de exemplo em `INOVAAPPS_base_de_dados.xlsx`).

Cada empresa faz login no seu próprio contexto, envia sua planilha de clientes, configura pesos e limites de risco e vê um painel com o índice de atenção, exposição financeira e fila de atendimento, com tema visual próprio e um assistente de IA isolado por empresa.

---

## ✨ Funcionalidades

- **Multi-tenancy**: usuário vinculado a uma empresa (`company_id`); detecção opcional por subdomínio (`acme.app.com`). Dados, chat e configurações são isolados por empresa.
- **Importação de planilhas** (XLSX/CSV) com mapeamento dinâmico de colunas. Colunas que não fazem parte do modelo padrão viram **métricas próprias da empresa** (veja abaixo). Arquivos grandes (> 2 MB) rodam em fila.
- **Motor de risco** determinístico e explicável: **índice de atenção** de 0 a 100 (não é probabilidade de cancelamento) a partir de sinais (uso da plataforma, SLA, NPS, chamados, reuniões, tendência etc.) **e das métricas de cada empresa**, com níveis Baixo/Médio/Alto/Crítico.
- **Fila de atendimento** em dois grupos: quem já está em alerta vem primeiro; em cada grupo, atenção × (atenção + K) × valor do contrato, com `K` configurável. O prazo de contato segue a posição na fila. Um cliente pode ser marcado como **resolvido** (vai para o fim da fila) e reaberto depois.
- **Configurações por empresa**: ordem/peso/ativação dos sinais, limites dos níveis, tema (cores, fonte, logo). Alterações recalculam a carteira.
- **Painel**: KPIs, clientes por nível, risco por segmento, uso × SLA, comparação com cancelados e fila de atendimento.
- **Assistente de chat com IA**: API externa da NVIDIA (prioridade), Ollama local como segunda opção e respostas por regras como último recurso; histórico filtrado por empresa.
- **Relatório em PDF** por empresa (DomPDF) e **notificações**.
- **Acessibilidade**: widget **VLibras** (Libras), tamanho de texto ajustável, navegação por comando de voz e **conversa por voz** com o assistente (voz neural via Edge TTS).

## 📊 Métricas por empresa

Cada empresa tem o seu próprio conjunto de métricas, definido a partir da base que ela envia. No envio da planilha, cada coluna nova é mapeada para uma métrica existente ou cadastrada como nova (também dá para cadastrar em Configurações). A métrica guarda nome, descrição, tipo, direção de piora, valores saudável e crítico e peso. Alterar peso, faixa ou estado recalcula a atenção da carteira.

| Tipo | Como o valor é lido | Entra na atenção? |
|---|---|---|
| Número decimal | Número com casas decimais (`12,5`) | Sim |
| Número inteiro (contagem) | Só inteiros (`3`) | Sim |
| Percentual | 0 a 100, com ou sem `%` | Sim |
| Valor monetário | Aceita `R$ 1.200,50` | Sim |
| Binário | `0` ou `1` (também `sim`/`não`) | Sim |
| Nota | 0 a 10 | Sim |
| Data | `AAAA-MM-DD` ou `DD/MM/AAAA`, guardada como `AAAA-MM-DD` (útil para data de início e de fim) | Não |
| Texto | Até 1000 caracteres | Não |

### Modelo de planilha

Em **Planilha** há o modelo completo em XLSX, no formato da base do desafio: uma aba **Leia-me** com as instruções, um **dicionário** dos campos e abas de dados (`clientes` e `metricas_mensais`). Dá para usar quantas abas quiser, desde que todas tenham a coluna `cliente_id`: abas com `mes_ref` são mensais e abas sem ele valem para todos os meses do cliente (a mesma coluna não pode aparecer em duas abas).

Se o dicionário vier preenchido (`campo`, `tipo`, `descricao` e, para métricas que entram na atenção, `piora_quando`, `valor_saudavel`, `valor_critico` e `peso`), as métricas já chegam configuradas na tela de confirmação. O dicionário da base do desafio também funciona: os tipos (Inteiro, Decimal (%), Binario 0/1, Data...) e as descrições são lidos dele.

Valores vazios são aceitos: o mês fica no histórico sem aquela métrica. Métricas de tipo Data e Texto aparecem no histórico do cliente, mas ficam fora do cálculo.

## 🧱 Stack

PHP 8.3+ · Laravel 13 · Filament 5 · Livewire 4 · Tailwind CSS 4 · Vite 8 · OpenSpout (XLSX) · DomPDF · SQLite (padrão) · PHPUnit 12

---

## 📋 Pré-requisitos

| Ferramenta | Versão |
|---|---|
| PHP | 8.3+ (extensões usuais do Laravel, `sqlite`, `zip`, `gd`) |
| Composer | 2.x |
| Node.js + npm | 18+ |
| Ollama *(opcional, para o chat local)* | https://ollama.com |

---

## ▶️ Como rodar

O código Laravel fica na pasta [`app/`](app/).

```bash
git clone https://github.com/MatheusIngles/InovaApps2026.git
cd InovaApps2026/app

# Atalho: instala dependências, cria .env, gera chave, migra e faz build
composer setup
```

Ou manualmente:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan storage:link      # necessário para servir os logos enviados
```

### Popular com dados de demonstração

```bash
php artisan db:seed
```

Cria as empresas **demo** (com a base do desafio já importada e riscos calculados) e **beta** (vazia, com tema próprio, ideal para testar o envio de planilha).

| Usuário | Senha | Empresa |
|---|---|---|
| `admin@inova.com` | `senha123` | Demo |
| `demo@inova.com` | `senha123` | Demo |
| `admin@beta.com` | `senha123` | Beta |

### Subir o ambiente de desenvolvimento

```bash
composer dev
```

Alternativa em terminais separados:

```bash
php artisan serve      # http://localhost:8000
npm run dev            # Vite com hot-reload
php artisan queue:work # obrigatório para importações grandes
```

Acesse **http://localhost:8000** e entre com um dos usuários acima. Empresa sem dados cai na tela de planilha; com dados, na lista de empresas/clientes.

---

## ⚙️ Configuração (`.env`)

| Variável | Descrição |
|---|---|
| `DB_CONNECTION` | `sqlite` por padrão; pode usar MySQL/PostgreSQL |
| `TENANT_BASE_DOMAIN` | Domínio base para detectar a empresa pelo subdomínio. Vazio = desativado |
| `LLM_ENABLED` | Liga/desliga o assistente de IA |
| `OLLAMA_URL` / `OLLAMA_MODEL` | Modelo local (padrão `llama3.1` em `localhost:11434`) |
| `LLM_API_URL` / `LLM_API_KEY` / `LLM_API_MODEL` | API externa da NVIDIA (formato OpenAI), a primeira opção do chat; sem chave, usa o Ollama |
| `EDGE_TTS_PYTHON` / `EDGE_TTS_VOZ` | Voz neural do modo conversa por voz: precisa de `pip install edge-tts` no servidor. Sem ela, o navegador lê com a voz local |
| `QUEUE_CONNECTION` | `database` por padrão; mantenha um worker ativo |

Para usar o chat local:

```bash
ollama pull llama3.1
ollama serve
```

---

## 🧪 Testes e qualidade

```bash
composer test                 # limpa config e roda a suíte
php artisan test --filter=TenancyTest
vendor/bin/pint               # formatação (Laravel Pint)
```

Cobertura atual: isolamento de tenants, importação, configuração de empresa, carteira, relatório e notificações.

---

## 🗂️ Estrutura

```
InovaApps/
├── Desafio - INOVAAPPS 2026.pdf     # enunciado
├── INOVAAPPS_base_de_dados.xlsx     # base de exemplo
├── todo_checklist_inova.md          # checklist de requisitos
├── .github/workflows/deploy.yaml    # deploy automático via SSH
└── app/                             # projeto Laravel
    ├── app/Filament/                # Painel, Planilha, Configurações, Assistente, Empresas
    ├── app/Livewire/                # AssistenteChat, ImportarPlanilha, RelatorioEmpresa
    ├── app/Jobs/                    # ImportarPlanilhaJob (fila)
    ├── app/Models/                  # Company, Customer, CustomerMetric, CustomerNps, RiskAssessment...
    ├── app/Support/                 # Risco, RiskService, Import, Llm, Tenancy, Relatorio, Notificacoes
    ├── database/{migrations,seeders}
    ├── docs/                        # documentação técnica e funcional
    └── tests/
```

## 📚 Documentação

- [Arquitetura e funcionamento](app/docs/ARQUITETURA-E-FUNCIONAMENTO.md): camadas, modelo de dados, limitações conhecidas.
- [Guia funcional do painel](app/docs/GUIA-FUNCIONAL-DO-PAINEL.md): como cada métrica, peso e nível é calculado.
- [Checklist do desafio](todo_checklist_inova.md): requisitos e status.

## 🚢 Deploy

Push na `main` dispara [`deploy.yaml`](.github/workflows/deploy.yaml): conecta ao servidor via SSH, faz `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `optimize` e recarrega o serviço. Requer os secrets `SERVER_HOST`, `SERVER_USER`, `SSH_PRIVATE_KEY` e `SUDO_PASSWORD`.

## ♿ VLibras

O widget do [VLibras](https://www.gov.br/governodigital/pt-br/vlibras) (Governo Federal) traduz o conteúdo para Libras. Carrega os assets de `vlibras.gov.br`, portanto exige internet.

## 🔗 Links úteis

[Laravel](https://laravel.com/docs) · [Filament](https://filamentphp.com/docs) · [Livewire](https://livewire.laravel.com) · [Tailwind CSS](https://tailwindcss.com/docs) · [Ollama](https://ollama.com)
