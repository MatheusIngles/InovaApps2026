<?php

namespace App\Filament\Pages;

use App\Support\Risco;
use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\Tema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Component as Livewire;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Configuração da empresa: prioridade/peso das métricas, limiares dos níveis e tema. */
class Configuracoes extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.configuracoes';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(CompanyConfig::ler(app(CompanyContext::class)->current()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Prioridade das métricas')
                ->description('Ordene da mais para a menos importante: a que fica no topo pesa mais. Desligue uma métrica para ignorá-la no cálculo; ligue de novo quando quiser voltar a usá-la.')
                ->schema([
                    Repeater::make('metricas')->hiddenLabel()->addable(false)->deletable(false)->reorderable()->reorderableWithButtons()
                        ->itemLabel(fn (array $state): ?string => Risco::ROTULOS[$state['k'] ?? ''] ?? null)
                        ->schema([Hidden::make('k'), Toggle::make('ativa')->label('Considerar no cálculo do risco')->default(true)]),
                ]),
            Section::make('Fila de prioridade')
                ->description('A fila ordena por score × (score + K) × valor do contrato. K controla o que pesa mais: menor, o score manda; maior, o valor do contrato manda.')
                ->schema([
                    TextInput::make('prioridade')->label('Constante K (0 a 500)')->numeric()->integer()->minValue(0)->maxValue(500)->step(5)->required()
                        ->helperText('0: só o risco decide. 50 (padrão): equilíbrio. 200 ou mais: quase só o valor do contrato decide.'),
                ]),
            Section::make('Níveis de risco')->description('Score mínimo (0 a 100) de cada nível.')->columns(3)->schema([
                TextInput::make('limiares.critico')->label('Crítico a partir de')->numeric()->required(),
                TextInput::make('limiares.alto')->label('Alto a partir de')->numeric()->required(),
                TextInput::make('limiares.medio')->label('Médio a partir de')->numeric()->required(),
            ]),
            Section::make('Identidade visual')->description('Cores, fonte e logo aplicados a todo o painel desta empresa.')->schema([
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    ColorPicker::make('tema.primary')->label('Cor primária')->required(),
                    ColorPicker::make('tema.secondary')->label('Cor secundária')->required(),
                    TextInput::make('tema.brand')->label('Nome exibido no painel')->placeholder('Seer')->maxLength(40)->helperText('Vazio = Seer.'),
                ]),
                FileUpload::make('tema.logo')->label('Logo')->image()->disk('public')->directory('logos')->maxSize(1024)
                    ->helperText('Ao enviar, as cores do painel mudam para as do logo. Você pode ajustá-las.'),
                View::make('filament.components.conta-gotas')
                    ->afterStateUpdated(function ($state, Livewire $livewire) {
                        $arquivo = is_array($state) ? Arr::first($state) : $state;

                        // a cor é lida no navegador (canvas): não depende de extensão do PHP
                        if ($arquivo instanceof TemporaryUploadedFile) {
                            $livewire->js('window.corDoLogo($wire, '.json_encode($arquivo->temporaryUrl()).')');
                        }
                    }),
            ]),
        ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();
        // o peso vem da posição: a escala padrão (20, 15, 15, 12...) distribuída na ordem escolhida
        // métricas desligadas ficam com peso 0 e não ocupam posição na escala
        $escala = collect(Risco::PESOS)->sortDesc()->values();
        $i = 0;
        $metricas = [];

        foreach (array_values($dados['metricas']) as $metrica) {
            $metricas[] = [
                'k' => $metrica['k'],
                'peso' => ($metrica['ativa'] ?? true) ? $escala[$i++] : 0,
            ];
        }

        $dados['metricas'] = $metricas;
        $dados['tema']['logo'] = is_array($dados['tema']['logo'] ?? null) ? Arr::first($dados['tema']['logo']) : ($dados['tema']['logo'] ?? null);

        try {
            $recalculou = CompanyConfig::salvar(app(CompanyContext::class)->current(), $dados);
        } catch (ValidationException $e) {
            Notification::make()->title('Configuração inválida')->body(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        Notification::make()->title('Configuração salva')->body($recalculou ? 'O risco de todos os clientes foi recalculado.' : null)->success()->send();
        $this->redirect(static::getUrl()); // recarrega já com o novo tema
    }

    public function restaurar(): void
    {
        CompanyConfig::restaurar(app(CompanyContext::class)->current());
        Notification::make()->title('Configuração padrão restaurada')->success()->send();
        $this->redirect(static::getUrl());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restaurar')->label('Restaurar padrão')->color('gray')->requiresConfirmation()
                ->modalDescription('Remove pesos, limiares e tema personalizados desta empresa.')->action(fn () => $this->restaurar()),
        ];
    }
}
