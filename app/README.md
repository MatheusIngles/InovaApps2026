# Seer: como instalar e rodar

Este é o projeto Laravel do Seer. A visão geral do produto está no [README da raiz](../README.md); aqui fica só o que precisa para baixar, configurar e compilar.

**Stack:** PHP 8.3+ · Laravel 13 · Filament 5 · Livewire 4 · Tailwind CSS 4 · Vite · SQLite (padrão).

## Pré-requisitos

| Ferramenta | Versão / observação |
|---|---|
| PHP | 8.3+ com as extensões `sqlite3`, `pdo_sqlite`, `zip`, `gd`, `intl`, `mbstring`, `curl`, `xml` |
| Composer | 2.x |
| Node.js + npm | 18+ |
| Ollama *(opcional)* | IA local do chat: https://ollama.com |
| Python + `edge-tts` *(opcional)* | voz neural do modo conversa |

## Instalação

```bash
git clone https://github.com/MatheusIngles/InovaApps2026.git
cd InovaApps2026/app
composer setup
```

O `composer setup` instala as dependências, cria o `.env`, gera a chave, roda as migrations e faz o build do front. Se preferir passo a passo:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # no Windows: type nul > database\database.sqlite
php artisan migrate
npm install
npm run build
php artisan storage:link            # necessário para exibir os logos enviados
```

### Dados de demonstração

```bash
php artisan db:seed
```

Importa a base do desafio (`../dados/INOVAAPPS_base_de_dados.xlsx`) e cria as empresas **Globalsys** (com dados) e **Beta** (vazia, para testar o envio de planilha).

| Usuário | Senha | Empresa |
|---|---|---|
| `admin@inova.com` | `senha12345senha` | Globalsys (base do desafio) |
| `demo@inova.com` | `senha12345senha` | Globalsys (base do desafio) |
| `admin@beta.com` | `senha12345senha` | Beta |

Essas senhas valem só para desenvolvimento. Não rode o seeder em produção.

### Subir o ambiente

```bash
composer dev
```

Ou em terminais separados:

```bash
php artisan serve        # http://localhost:8000
npm run dev              # Vite com hot-reload
php artisan queue:work   # importações grandes e relatórios rodam em fila
```

## Configuração (`.env`)

O `.env` nunca vai para o Git (está no `.gitignore`). O modelo está em [`.env.example`](.env.example). As variáveis principais:

| Variável | Descrição |
|---|---|
| `APP_URL` | URL pública. Com `https://`, os links e assets saem em HTTPS |
| `APP_ENV` / `APP_DEBUG` | Em produção: `production` e `false` |
| `DB_CONNECTION` | `sqlite` por padrão; aceita MySQL/PostgreSQL |
| `QUEUE_CONNECTION` | `database` por padrão; mantenha um worker ativo |
| `TENANT_BASE_DOMAIN` | Domínio base para achar a empresa pelo subdomínio. Vazio = desligado |
| `TRUSTED_PROXIES` | IPs do proxy reverso, se ele estiver fora da rede privada |
| `LLM_ENABLED` | Liga/desliga o assistente de IA |
| `LLM_API_URL` / `LLM_API_KEY` / `LLM_API_MODEL` | API externa da NVIDIA (formato OpenAI): primeira opção do chat |
| `OLLAMA_URL` / `OLLAMA_MODEL` | IA local: segunda opção. Sem nenhuma das duas, o chat responde por regras |
| `EDGE_TTS_PYTHON` / `EDGE_TTS_VOZ` | Voz neural. Sem ela, o navegador lê com a voz local |

### IA do chat

A ordem é: API da NVIDIA → Ollama → respostas por regras.

> **Ressalva sobre a API da NVIDIA.** O Seer usa a API do [build.nvidia.com](https://build.nvidia.com), que é **gratuita para desenvolvimento, testes e prototipagem**, com limite de requisições e sem garantia de disponibilidade. Por isso ela é a primeira opção, mas nunca a única: se a chave não existir, o limite estourar ou a resposta vier fraca, o sistema usa o Ollama e, por último, respostas por regras. Para uso comercial em produção, confirme os termos e os planos da NVIDIA antes de depender dela, ou use só o Ollama local (sem custo e sem enviar dados para fora).

Para conseguir a chave gratuita, crie uma conta em https://build.nvidia.com, gere uma API key e coloque em `LLM_API_KEY` no `.env`. Para o Ollama local:

```bash
ollama pull llama3.1
ollama serve
```

### Voz neural (opcional)

O modo conversa do chat lê as respostas em voz alta. A voz neural vem do **Edge TTS**, um pacote Python que o servidor chama (`python -m edge_tts`). Sem ele, nada quebra: o navegador lê com a voz do próprio sistema, mais robótica.

O que precisa:
- **Python 3** no servidor e **internet de saída** (o Edge TTS gera o áudio nos serviços da Microsoft; é gratuito, mas não é um serviço oficial e pode mudar).
- O pacote `edge-tts` instalado em um ambiente virtual (o Ubuntu 24.04 não deixa instalar direto com `pip`).

**Linux (Ubuntu/Debian):**

```bash
sudo apt install -y python3 python3-venv
sudo python3 -m venv /opt/edge-tts
sudo /opt/edge-tts/bin/pip install edge-tts
```

Depois, no `.env`:

```
EDGE_TTS_PYTHON=/opt/edge-tts/bin/python
# EDGE_TTS_VOZ=pt-BR-FranciscaNeural   (opcional; outras: pt-BR-AntonioNeural, pt-BR-ThalitaMultilingualNeural)
```

Rode `php artisan optimize:clear`. Para testar, o comando abaixo deve criar um MP3 em `/tmp`:

```bash
/opt/edge-tts/bin/python -m edge_tts --voice pt-BR-FranciscaNeural --text "Olá, eu sou o Seer" --write-media /tmp/teste.mp3 && ls -l /tmp/teste.mp3
```

**Windows (desenvolvimento):** `pip install edge-tts` e `EDGE_TTS_PYTHON=python` (o padrão). O usuário do PHP precisa conseguir executar esse Python.

Se o áudio não tocar, abra o chat, ligue o microfone e veja no navegador (F12 › Rede) a resposta de `/assistente/voz`: `503` significa que o Python ou o pacote não foi encontrado, ou que o servidor está sem internet.

Requisitos do navegador: o reconhecimento de fala (microfone) funciona no Chrome e no Edge, em HTTPS ou `localhost`.

## Como guardar e compartilhar o `.env` sem vazar

Nunca faça commit do `.env`. Duas formas seguras de levá-lo para outra máquina:

**1. Criptografado no repositório (recurso nativo do Laravel).** O arquivo cifrado pode ir para o Git; só quem tem a chave lê.

```bash
php artisan env:encrypt --env=production      # gera .env.production.encrypted e mostra a chave
```

Guarde a chave em um gerenciador de senhas ou mande por um canal privado, nunca no repositório. Para restaurar em outra máquina:

```bash
php artisan env:decrypt --env=production --key=SUA_CHAVE
```

**2. Só pelo servidor ou pelos secrets do GitHub.** O `.env` fica direto no servidor e não passa pelo Git. Para o deploy, use *Settings → Secrets and variables → Actions*.

Se uma chave (como a `LLM_API_KEY`) já foi colada em chat, print ou commit, gere outra no painel do provedor e troque no `.env`.

## Testes e qualidade

```bash
composer test                 # limpa a config e roda a suíte
php artisan test --filter=SegurancaTest
vendor/bin/pint               # formatação (Laravel Pint)
```

No Windows, se faltar a extensão `intl`: `php -d extension=intl vendor/bin/phpunit`.

## Build e deploy

```bash
npm run build                          # gera public/build
php artisan migrate --force
php artisan optimize                   # cache de config, rotas e views
php artisan queue:restart
```

Em produção, o push na `main` roda o workflow [`deploy.yaml`](../.github/workflows/deploy.yaml). Depois de mudar o `.env`, rode `php artisan optimize:clear` e reinicie o servidor.

Se algo não aparecer (logo quebrado, CSS sem carregar), confira `storage:link`, o `APP_URL` e se o `npm run build` foi feito.
