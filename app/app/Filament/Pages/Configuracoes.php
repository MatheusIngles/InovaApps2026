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
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
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
            Tabs::make('Configurações')->tabs([
                Tab::make('Prioridades')->schema([
                    Section::make('Peso de cada métrica')
                        ->description('Arraste de 0 a 20. Zero ignora a métrica. Os valores são relativos: a soma é normalizada para formar o score de 0 a 100.')
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2])->schema(
                                collect(Risco::ROTULOS)->map(fn (string $rotulo, string $chave) => Slider::make("pesos.{$chave}")
                                    ->label($rotulo)->range(0, 20)->step(0.1)->tooltips()->pips()->live()
                                    ->hint(fn (Get $get): string => number_format((float) $get(''), 1, ',', '.').' / 20')
                                )->values()->all()
                            ),
                        ]),
                ]),
                Tab::make('Níveis de risco')->schema([
                    Section::make('Limites dos níveis')->description('Score mínimo (0 a 100) de cada nível.')->columns(3)->schema([
                        TextInput::make('limiares.critico')->label('Crítico a partir de')->numeric()->required(),
                        TextInput::make('limiares.alto')->label('Alto a partir de')->numeric()->required(),
                        TextInput::make('limiares.medio')->label('Médio a partir de')->numeric()->required(),
                    ]),
                ]),
                Tab::make('Identidade visual')->schema([
                    Section::make('Aparência')->description('Cores, fonte e logo aplicados a todo o painel desta empresa.')->schema([
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

                                if ($arquivo instanceof TemporaryUploadedFile) {
                                    $livewire->js('window.corDoLogo($wire, '.json_encode($arquivo->temporaryUrl()).')');
                                }
                            }),
                    ]),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();
        $dados['metricas'] = collect(Risco::PESOS)->keys()
            ->mapWithKeys(fn (string $chave): array => [$chave => (float) ($dados['pesos'][$chave] ?? 0)])
            ->sortDesc()
            ->map(fn (float $peso, string $chave): array => ['k' => $chave, 'peso' => $peso])
            ->values()->all();
        unset($dados['pesos']);
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
