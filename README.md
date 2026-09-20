# InovaApps 2026

Plataforma **multi-tenant** de gestão de carteira de clientes, saúde da conta e priorização de atendimento, desenvolvida para o desafio **INOVAAPPS 2026** (enunciado em `Desafio - INOVAAPPS 2026.pdf`, base de dados de exemplo em `INOVAAPPS_base_de_dados.xlsx`).

Cada empresa faz login no seu próprio contexto, envia sua planilha de clientes, configura pesos e limites de risco e vê um painel com score, exposição financeira e fila de atendimento, com tema visual próprio e um assistente de IA isolado por empresa.

---

## ✨ Funcionalidades

- **Multi-tenancy**: usuário vinculado a uma empresa (`company_id`); detecção opcional por subdomínio (`acme.app.com`). Dados, chat e configurações são isolados por empresa.
- **Importação de planilhas** (XLSX/CSV) com mapeamento dinâmico de colunas. Arquivos grandes (> 2 MB) rodam em fila.
- **Motor de risco** determinístico e explicável: score 0–100 a partir de sinais (uso da plataforma, SLA, NPS, chamados, reuniões, tendência etc.), níveis Baixo/Médio/Alto/Crítico.
- **Prioridade da fila** por risco × valor do contrato, com constante `K` configurável.
- **Configurações por empresa**: ordem/peso/ativação dos sinais, limites dos níveis, tema (cores, fonte, logo). Alterações recalculam a carteira.
- **Painel**: KPIs, clientes por nível, risco por segmento, uso × SLA, comparação com cancelados e fila de atendimento.
- **Assistente de chat com IA**: API externa da NVIDIA (prioridade), Ollama local como segunda opção e respostas por regras como último recurso; histórico filtrado por empresa.
- **Relatório em PDF** por empresa (DomPDF) e **notificações**.
- **Acessibilidade**: widget **VLibras** (Libras).

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
