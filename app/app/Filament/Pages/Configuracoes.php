<?php

namespace App\Filament\Pages;

use App\Support\Risco;
use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\Tema;
use BackedEnum;
use Closure;
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

    protected static ?string $title = 'Configurações';

    protected static bool $shouldRegisterNavigation = false; // abre pelo menu do avatar

    protected static ?int $navigationSort = 5;

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
                ...($this->hasLegacyMetrics() ? [Tab::make('Prioridades')->schema([
                    Section::make('Prioridade dos sinais padrão')
                        ->description('Arraste os oito sinais padrão para ordenar: o que fica no topo pesa mais. Use "Editar pesos" para definir o peso de cada um. As métricas próprias têm pesos na aba ao lado.')
                        ->headerActions([$this->editarPesosAction()])
                        ->schema([
                            Repeater::make('metricas')->hiddenLabel()->addable(false)->deletable(false)->reorderable()
                                ->itemLabel(fn (array $state): ?string => Risco::ROTULOS[$state['k'] ?? ''] ?? null)
                                ->schema([Hidden::make('k'), Hidden::make('peso'), Toggle::make('ativa')->label('Considerar no cálculo da atenção')->default(true)]),
                            View::make('filament.components.salvar-configuracao'),
                        ]),
                ])] : []),
                Tab::make('Métricas')->schema([
                    View::make('filament.components.metricas-proprias'),
                ]),
                Tab::make('Fila de prioridade')->schema([
                    Section::make('Ordem da lista de clientes')
                        ->description('A fila mostra primeiro os clientes já em alerta (nível Médio ou acima) e depois os demais; em cada grupo, ordena por atenção × (atenção + K) × valor mensal do contrato. K baixo reforça a diferença de atenção; K alto aproxima a ordem de atenção × contrato.')
                        ->schema([
                            TextInput::make('prioridade')->label('Equilíbrio da fila (K)')->numeric()->integer()->minValue(0)->maxValue(500)->step(5)->required()
                                ->helperText('De 0 a 500. O padrão é 50; o valor do contrato participa da ordem em toda a faixa.'),
                        ]),
                    View::make('filament.components.salvar-configuracao'),
                ]),
                Tab::make('Níveis de atenção')->schema([
                    Section::make('Limites dos níveis')->description('Atenção mínima (0 a 100) de cada nível.')->columns(3)->schema([
                        TextInput::make('limiares.critico')->label('Crítico a partir de')->numeric()->required(),
                        TextInput::make('limiares.alto')->label('Alto a partir de')->numeric()->required(),
                        TextInput::make('limiares.medio')->label('Médio a partir de')->numeric()->required(),
                    ]),
                    View::make('filament.components.salvar-configuracao'),
                ]),
                Tab::make('Identidade visual')->schema([
                    Section::make('Aparência')->description('Cores, fonte e logo aplicados a todo o painel desta empresa.')->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            ColorPicker::make('tema.primary')->label('Cor primária')->required(),
                            ColorPicker::make('tema.secondary')->label('Cor secundária')->required(),
                            TextInput::make('tema.brand')->label('Nome exibido no painel')->placeholder('Seer')->maxLength(40)->helperText('Vazio = Seer.'),
                        ]),
                        FileUpload::make('tema.logo')->label('Logo')->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->disk('public')->directory('logos')->maxSize(1024)
                            ->helperText('Ao enviar, as cores do painel mudam para as do logo. Você pode ajustá-las.'),
                        View::make('filament.components.conta-gotas')
                            ->afterStateUpdated(function ($state, Livewire $livewire) {
                                $arquivo = is_array($state) ? Arr::first($state) : $state;

                                if ($arquivo instanceof TemporaryUploadedFile) {
                                    $livewire->js('window.corDoLogo($wire, '.json_encode($arquivo->temporaryUrl()).')');
                                }
                            }),
                    ]),
                    View::make('filament.components.salvar-configuracao'),
                ]),
                Tab::make('Assistente de IA')->schema([
                    Section::make('Uso de inteligência artificial')
                        ->description('Com a IA desligada, nada da sua carteira é enviado a provedores externos: o chat responde às perguntas prontas com regras fixas sobre os dados da empresa, e o relatório e a explicação de configuração usam textos por regras. A atenção e a fila não mudam, porque não dependem de IA.')
                        ->schema([
                            Toggle::make('ia')->label('Usar o assistente de IA')->live()
                                ->afterStateUpdated(function (bool $state): void {
                                    CompanyConfig::definirIa(app(CompanyContext::class)->current(), $state);
                                    Notification::make()->title($state ? 'IA ligada' : 'IA desligada: respostas por regras')->success()->send();
                                }),
                        ]),
                ]),
                Tab::make('Acrescentar novos meses')->schema([
                    View::make('filament.components.novos-meses'),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    private function hasLegacyMetrics(): bool
    {
        return app(CompanyContext::class)->current()->hasLegacyMetrics();
    }

    /** Os pesos pertencem às posições da lista (1º = maior peso), não às métricas: o modal edita a escala, de forma decrescente. */
    private function editarPesosAction(): Action
    {
        $posicoes = fn (): int => max(1, collect($this->data['metricas'] ?? [])->where('ativa', true)->count());

        return Action::make('editarPesos')->label('Editar pesos')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
            ->modalHeading('Editar pesos por posição')
            ->modalDescription('Cada posição da lista tem um peso: a 1ª pesa mais. Os pesos não podem aumentar de uma posição para a seguinte. Para trocar quem ocupa cada posição, arraste as métricas. Depois clique em Salvar para aplicar.')
            ->modalSubmitActionLabel('Aplicar')
            ->fillForm(fn (): array => ['pos' => collect(CompanyConfig::pesosPorPosicao($this->data['metricas'] ?? []))->where('peso', '>', 0)->pluck('peso')->values()->all()])
            ->schema(fn (): array => collect(range(0, $posicoes() - 1))->map(fn (int $i) => TextInput::make("pos.$i")
                ->label('Peso da prioridade '.($i + 1))
                ->numeric()->minValue(1)->maxValue(100)->required()
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $i): void {
                    if ($i > 0 && (float) $value > (float) $get('pos.'.($i - 1))) {
                        $fail('Não pode ser maior que o peso da posição anterior.');
                    }
                }))->all())
            ->action(function (array $data): void {
                $i = 0;
                $metricas = collect($this->data['metricas'] ?? [])
                    ->map(fn (array $m) => ['k' => $m['k'], 'ativa' => $m['ativa'] ?? true, 'peso' => ($m['ativa'] ?? true) ? (float) $data['pos'][$i++] : 0])
                    ->values()->all();
                $this->form->fill([...$this->data, 'metricas' => $metricas]);
            });
    }

    public function salvar(): void
    {
        $company = app(CompanyContext::class)->current();
        $dados = array_replace_recursive(CompanyConfig::ler($company), $this->form->getState());
        if ($this->hasLegacyMetrics()) {
            $dados['metricas'] = CompanyConfig::pesosPorPosicao($dados['metricas']); // o peso vem da posição na lista
        }
        $dados['tema']['logo'] = is_array($dados['tema']['logo'] ?? null) ? Arr::first($dados['tema']['logo']) : ($dados['tema']['logo'] ?? null);

        try {
            $recalculou = CompanyConfig::salvar($company, $dados);
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
