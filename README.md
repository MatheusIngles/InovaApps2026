# InovaApps2026

Projeto Laravel com **Tailwind CSS** e o widget de acessibilidade **VLibras** já configurados e funcionando.

O código do projeto Laravel está na pasta [`app/`](app/).

---

## 📋 Pré-requisitos

Antes de instalar o Laravel, você precisa ter instalado na sua máquina:

| Ferramenta | Versão mínima | Link |
|---|---|---|
| PHP | 8.2+ | https://www.php.net/downloads |
| Composer | 2.x | https://getcomposer.org/download/ |
| Node.js + npm | 18+ | https://nodejs.org/ |
| Git | qualquer | https://git-scm.com/downloads |

Verifique se estão corretamente instalados rodando:

```bash
php -v
composer -V
node -v
npm -v
```

> 💡 Opcional: você também pode instalar um banco de dados como MySQL/PostgreSQL. Este projeto já vem pronto para usar **SQLite**, que não exige instalação de servidor de banco de dados.

---

## 🚀 Passo a passo: como instalar o Laravel do zero

Caso queira criar um projeto Laravel novo (do zero) na sua máquina, o passo a passo é:

### 1. Instalar o instalador do Laravel (opcional, mas recomendado)

```bash
composer global require laravel/installer
```

### 2. Criar um novo projeto Laravel

Usando o instalador do Laravel:

```bash
laravel new nome-do-projeto
```

Ou diretamente via Composer (sem precisar do instalador):

```bash
composer create-project laravel/laravel nome-do-projeto
```

### 3. Entrar na pasta do projeto

```bash
cd nome-do-projeto
```

### 4. Configurar o arquivo de ambiente

O Composer já cria o `.env` automaticamente a partir do `.env.example` e gera a `APP_KEY`. Caso precise fazer manualmente:

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Criar o banco de dados (SQLite, padrão do Laravel 12)

```bash
touch database/database.sqlite
php artisan migrate
```

### 6. Instalar as dependências de front-end e subir o Tailwind/Vite

```bash
npm install
npm run build
```

### 7. Subir o servidor local

```bash
php artisan serve
```

Acesse **http://localhost:8000** no navegador. 🎉

---

## ▶️ Como rodar ESTE projeto (o que já está no repositório)

Este repositório já contém um projeto Laravel pronto, configurado com Tailwind CSS e VLibras. Para rodá-lo na sua máquina:

```bash
# 1. Clone o repositório (se ainda não tiver feito)
git clone https://github.com/<seu-usuario>/InovaApps2026.git
cd InovaApps2026/app

# 2. Instale as dependências PHP
composer install

# 3. Configure o ambiente
cp .env.example .env
php artisan key:generate

# 4. Crie o banco SQLite e rode as migrations
touch database/database.sqlite
php artisan migrate

# 5. Instale as dependências JS
npm install

# 6. Suba o front-end (modo desenvolvimento, com hot-reload)
npm run dev
```

Em **outro terminal**, com o `npm run dev` rodando, suba o servidor PHP:

```bash
php artisan serve
```

Acesse **http://localhost:8000**. Você verá a página inicial com Tailwind CSS estilizando o layout e o ícone do **VLibras** (tradutor de Libras) no canto da tela.

---

## 🎨 Tailwind CSS

O Laravel 12 já vem com Tailwind CSS v4 integrado via Vite (`@tailwindcss/vite`), sem necessidade de `tailwind.config.js`. A configuração fica em:

- [`app/resources/css/app.css`](app/resources/css/app.css) — importa o Tailwind (`@import 'tailwindcss';`)
- [`app/vite.config.js`](app/vite.config.js) — plugin `tailwindcss()` do Vite

Basta usar as classes utilitárias do Tailwind normalmente nos arquivos `.blade.php`, por exemplo:

```html
<div class="bg-white rounded-2xl shadow-lg p-10 text-center">
    <h1 class="text-3xl font-bold text-red-600">Olá, Tailwind!</h1>
</div>
```

Comandos úteis:

```bash
npm run dev     # modo desenvolvimento com hot-reload
npm run build   # gera os assets otimizados para produção
```

---

## ♿ VLibras (acessibilidade em Libras)

O [VLibras](https://www.gov.br/governodigital/pt-br/vlibras) é o plugin oficial do Governo Federal que traduz o conteúdo do site para Língua Brasileira de Sinais (Libras).

Ele já está incluído em [`app/resources/views/welcome.blade.php`](app/resources/views/welcome.blade.php):

```html
<div vw class="enabled">
    <div vw-access-button class="active"></div>
    <div vw-plugin-wrapper>
        <div class="vw-plugin-top-wrapper"></div>
    </div>
</div>
<script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
<script>
    new window.VLibras.Widget('https://vlibras.gov.br/app');
</script>
```

Para usar o widget em **outras páginas/layouts**, basta copiar esse mesmo bloco de HTML para o final do `<body>` do layout desejado (ex: em um `layouts/app.blade.php`, se você criar um).

> ⚠️ O VLibras carrega os assets do avatar direto do site `vlibras.gov.br`, então é necessário acesso à internet para o widget funcionar.

---

## 📖 Tutorial básico do Laravel

Um resumo dos conceitos e comandos essenciais para começar a desenvolver com Laravel.

### Estrutura de pastas principais

```
app/
├── app/
│   ├── Http/
│   │   └── Controllers/   → Controllers da aplicação
│   └── Models/             → Models (Eloquent ORM)
├── database/
│   └── migrations/         → Migrations do banco de dados
├── resources/
│   ├── views/               → Views Blade (.blade.php)
│   ├── css/                 → Estilos (Tailwind)
│   └── js/                  → JavaScript
├── routes/
│   └── web.php              → Rotas da aplicação
└── public/                  → Ponto de entrada público (index.php)
```

### Rotas

As rotas ficam em `routes/web.php`. Exemplo básico:

```php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sobre', function () {
    return 'Página sobre';
});
```

### Artisan (linha de comando do Laravel)

O `artisan` é a CLI do Laravel, usada para gerar código e rodar tarefas:

```bash
php artisan serve                     # sobe o servidor local
php artisan make:controller NomeController   # cria um controller
php artisan make:model NomeModelo -m         # cria um model + migration
php artisan make:migration create_tabela_table  # cria uma migration
php artisan migrate                   # roda as migrations pendentes
php artisan migrate:fresh             # apaga tudo e recria o banco
php artisan tinker                    # abre um console interativo do Laravel
php artisan route:list                # lista todas as rotas
```

### Controllers

```bash
php artisan make:controller PostController
```

```php
// app/Http/Controllers/PostController.php
namespace App\Http\Controllers;

class PostController extends Controller
{
    public function index()
    {
        return view('posts.index');
    }
}
```

E na rota:

```php
use App\Http\Controllers\PostController;

Route::get('/posts', [PostController::class, 'index']);
```

### Models e Migrations (Eloquent ORM)

```bash
php artisan make:model Post -m
```

```php
// database/migrations/xxxx_create_posts_table.php
public function up(): void
{
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('titulo');
        $table->text('conteudo');
        $table->timestamps();
    });
}
```

```bash
php artisan migrate
```

Usando o Model:

```php
use App\Models\Post;

Post::create([
    'titulo' => 'Meu primeiro post',
    'conteudo' => 'Conteúdo do post...',
]);

$posts = Post::all();
```

### Views (Blade)

Arquivos `.blade.php` ficam em `resources/views`. O Blade permite lógica dentro do HTML:

```blade
{{-- resources/views/posts/index.blade.php --}}
<ul>
    @foreach ($posts as $post)
        <li>{{ $post->titulo }}</li>
    @endforeach
</ul>
```

### Variáveis do controller para a view

```php
public function index()
{
    $posts = Post::all();
    return view('posts.index', ['posts' => $posts]);
}
```

### Mais recursos

- Documentação oficial: https://laravel.com/docs
- Laracasts (vídeo-aulas): https://laracasts.com
- Documentação do VLibras: https://www.gov.br/governodigital/pt-br/vlibras
- Documentação do Tailwind CSS: https://tailwindcss.com/docs

---

## 🤖 Este README foi criado com o auxílio do Claude Code.
