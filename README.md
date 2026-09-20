# InovaApps 2026

Plataforma **multi-tenant** de gestão de carteira de clientes, saúde da conta e priorização de atendimento, desenvolvida para o desafio **INOVAAPPS 2026** (enunciado e base de exemplo na pasta [`dados/`](dados/)).

Cada empresa faz login no seu próprio contexto, envia sua planilha de clientes, configura pesos e limites de risco e vê um painel com o índice de atenção, exposição financeira e fila de atendimento, com tema visual próprio e um assistente de IA isolado por empresa.

---

## ✨ Funcionalidades

**Plataforma e acesso**
- **Multi-tenancy:** cada usuário pertence a uma empresa (`company_id`); dados, chat e configurações são isolados. Detecção opcional da empresa por subdomínio.
- **Login e cadastro** de empresa, com bloqueio por tentativas (10 erros em 15 minutos por e-mail), cabeçalhos de segurança, HSTS em HTTPS e proteção contra upload de arquivos inesperados.
- **Tema por empresa:** cores, fonte e logo próprios (com cores sugeridas a partir da logo), favicon e tela de login do Seer.
- **Carregamento com esqueleto** nos gráficos e seções enquanto renderizam.

**Dados e métricas**
- **Importação de planilhas** (XLSX/CSV) com várias abas ligadas por `cliente_id`, dicionário de campos e tela de confirmação em cartões. Arquivos grandes (> 2 MB) rodam em fila.
- **Modelo de planilha em XLSX** (Leia-me, dicionário e abas de dados) para baixar.
- **Métricas próprias por empresa,** com 8 tipos (decimal, inteiro, percentual, monetário, binário, nota, data e texto), direção de piora, faixas, peso e ativação. Mudar qualquer valor recalcula a carteira.
- **Colunas opcionais:** `segmento` e `plano` (viram "Não informado"), `situacao`, `mes_cancelamento` e `inicio_contrato` (reconhecem quem cancelou).
- **Sinais extras do desafio** (chamados críticos, tempo de resolução e volume de chamados) entram no cálculo quando a base tem esses dados.

**Análise**
- **Índice de atenção** de 0 a 100 (não é probabilidade de cancelamento), explicável, com níveis Baixo/Médio/Alto/Crítico.
- **Fila de atendimento** em dois grupos, com prazo de contato pela posição e cliente **marcado como resolvido** (vai para o fim da fila).
- **Painel** em abas (Visão geral, Gráficos e Por segmento): KPIs, clientes por nível, risco por segmento, uso × SLA e comparação com cancelados.
- **Validação com cancelados** (backtest) e **relatório de evidências em PDF** que funciona para qualquer empresa, com ou sem os 8 sinais padrão.
- **Notificações** de clientes que pioraram.

**Assistente de IA**
- **Chat** por empresa, opcionalmente focado em um cliente: API da NVIDIA (prioridade), Ollama local e respostas por regras como último recurso.
- **Conversa por voz** com voz neural (Edge TTS) e respostas menos formais, e **Libras:** o avatar do VLibras sinaliza as respostas da IA.
- **Acessibilidade:** widget VLibras, tamanho de texto ajustável e navegação por comando de voz.

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

Colunas obrigatórias: `cliente_id`, `mes_ref`, `porte` e `valor_mensal`. **`segmento` e `plano` são opcionais** (sem eles, ficam como "Não informado"). As colunas `situacao` (Ativo ou Cancelado), `mes_cancelamento` (AAAA-MM) e `inicio_contrato` não viram métricas: definem quem cancelou (o histórico e o cálculo param no mês anterior ao cancelamento) e o início do contrato. Sem elas, todos entram como ativos e a validação com cancelados não funciona.

Se o dicionário vier preenchido (`campo`, `tipo`, `descricao` e, para métricas que entram na atenção, `piora_quando`, `valor_saudavel`, `valor_critico` e `peso`), as métricas já chegam configuradas na tela de confirmação. O dicionário da base do desafio também funciona: os tipos (Inteiro, Decimal (%), Binario 0/1, Data...) e as descrições são lidos dele.

Valores vazios são aceitos: o mês fica no histórico sem aquela métrica. Métricas de tipo Data e Texto aparecem no histórico do cliente, mas ficam fora do cálculo.

## 🧱 Stack

PHP 8.3+ · Laravel 13 · Filament 5 · Livewire 4 · Tailwind CSS 4 · Vite 8 · OpenSpout (XLSX) · DomPDF · SQLite (padrão) · PHPUnit 12

---

## 🌐 Site no ar

Está publicado em **https://sitedemerda.com.br**.

O nome do domínio **não é proposital**. Era um domínio que um dos membros do time já tinha e usava para subir aplicações de teste. Para colocar o Seer em produção rápido, sem gastar tempo com registro e configuração de DNS de um domínio novo, a gente reaproveitou esse. Se o projeto seguir adiante, o certo é trocar por um domínio próprio (basta mudar `APP_URL` no `.env` e o proxy).

## 🖥️ Onde o site roda

O Seer roda em um servidor físico próprio, um PC de mesa (gabinete Multilaser) ligado à rede local. O domínio aponta para ele por um proxy reverso (Nginx Proxy Manager) com HTTPS.

![Servidor: gabinete Multilaser onde o Seer está hospedado](docs/imagens/servidor-frente.jpg)

| Item | Especificação |
|---|---|
| Processador | Intel Core i5-9400F @ 2,90 GHz (6 núcleos) |
| Memória RAM | 8 GB (7,7 GiB úteis) |
| Armazenamento | SSD NVMe de 240 GB (223,6 GiB) |
| Sistema operacional | Ubuntu 24.04.5 LTS |
| PHP | 8.4 (`php artisan serve` com 4 workers, mais `queue:work`, ambos via `nohup`) |

O selo "AMD FX" no gabinete é da carcaça antiga: o processador de hoje é Intel.

## ▶️ Como rodar

O código Laravel fica em [`app/`](app/). O passo a passo de instalação (dependências, `.env`, banco, build, dados de demonstração e testes) está em **[app/README.md](app/README.md)**.

Resumo:

```bash
git clone https://github.com/MatheusIngles/InovaApps2026.git
cd InovaApps2026/app
composer setup
php artisan db:seed
composer dev      # http://localhost:8000
```

Contas de demonstração (só para desenvolvimento; o seeder se recusa a rodar em produção):

| Usuário | Senha | Empresa |
|---|---|---|
| `admin@inova.com` | `senha123` | Globalsys (base do desafio) |
| `demo@inova.com` | `senha123` | Globalsys (base do desafio) |
| `admin@beta.com` | `senha123` | Beta (vazia, para testar o envio de planilha) |

## 🗂️ Estrutura

```
InovaApps2026/
├── dados/                           # dados do desafio e de teste
│   ├── Desafio - INOVAAPPS 2026.pdf # enunciado
│   ├── INOVAAPPS_base_de_dados.xlsx # base de exemplo (usada pelo seeder e pelos testes)
│   └── exemplo_planilha.csv         # planilha pequena para testar a importação
├── docs/                            # documentação técnica, funcional, negócio e checklist
├── .github/workflows/deploy.yaml    # deploy automático via SSH
└── app/                             # projeto Laravel (veja app/README.md)
    ├── app/Filament/                # Painel, Planilha, Configurações, Assistente, Empresas
    ├── app/Livewire/                # AssistenteChat, ImportarPlanilha, RelatorioEmpresa
    ├── app/Jobs/                    # ImportarPlanilhaJob (fila)
    ├── app/Models/                  # Company, Customer, MetricDefinition, MetricValue...
    ├── app/Support/                 # Risco, RiskService, Import, Llm, Tenancy, Relatorio, Metricas
    ├── database/{migrations,seeders}
    └── tests/
```

## 📚 Documentação

- [Arquitetura e funcionamento](docs/ARQUITETURA-E-FUNCIONAMENTO.md): camadas, modelo de dados, limitações conhecidas.
- [Guia funcional do painel](docs/GUIA-FUNCIONAL-DO-PAINEL.md): como cada métrica, peso e nível é calculado.
- [Modelo de negócio](docs/MODELO-DE-NEGOCIO.md)
- [Checklist do desafio](docs/checklist-do-desafio.md): requisitos e status.

## 🚢 Deploy

Push na `main` dispara [`deploy.yaml`](.github/workflows/deploy.yaml): conecta ao servidor via SSH, faz `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `optimize` e recarrega o serviço. Requer os secrets `SERVER_HOST`, `SERVER_USER`, `SSH_PRIVATE_KEY` e `SUDO_PASSWORD`.

## ♿ VLibras

O widget do [VLibras](https://www.gov.br/governodigital/pt-br/vlibras) (Governo Federal) traduz o conteúdo para Libras. Carrega os assets de `vlibras.gov.br`, portanto exige internet.

## 🔗 Links úteis

[Laravel](https://laravel.com/docs) · [Filament](https://filamentphp.com/docs) · [Livewire](https://livewire.laravel.com) · [Tailwind CSS](https://tailwindcss.com/docs) · [Ollama](https://ollama.com)
