<?php

namespace App\Filament\Pages;

use App\Support\Risco;
use App\Support\Tenancy\CompanyConfig;
use App\Support\Tenancy\CompanyContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/** Configuração da empresa: prioridade/peso das métricas, limiares dos níveis, tema e chat. */
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
                ->description('Arraste para ordenar (a ordem desempata sinais) e ajuste o peso de cada métrica no cálculo do risco. Os pesos são normalizados: não precisam somar 100.')
                ->schema([
                    Repeater::make('metricas')->hiddenLabel()->addable(false)->deletable(false)->reorderable()->reorderableWithButtons()
                        ->itemLabel(fn (array $state): ?string => Risco::ROTULOS[$state['k'] ?? ''] ?? null)
                        ->schema([Hidden::make('k'), TextInput::make('peso')->label('Peso (0 a 100)')->numeric()->minValue(0)->maxValue(100)->required()]),
                ]),
            Section::make('Níveis de risco')->description('Score mínimo (0 a 100) de cada nível.')->columns(3)->schema([
                TextInput::make('limiares.critico')->label('Crítico a partir de')->numeric()->required(),
                TextInput::make('limiares.alto')->label('Alto a partir de')->numeric()->required(),
                TextInput::make('limiares.medio')->label('Médio a partir de')->numeric()->required(),
            ]),
            Section::make('Identidade visual')->description('Cores, fonte e logo aplicados a todo o painel desta empresa.')->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    ColorPicker::make('tema.primary')->label('Cor primária')->required(),
                    ColorPicker::make('tema.secondary')->label('Cor secundária')->required(),
                    Select::make('tema.font')->label('Fonte')->options(array_combine(CompanyConfig::FONTES, CompanyConfig::FONTES))->required(),
                ]),
                FileUpload::make('tema.logo')->label('Logo')->image()->disk('public')->directory('logos')->maxSize(1024),
            ]),
            Section::make('Chat com IA')->description('Configurações isoladas desta empresa.')->schema([
                Toggle::make('chat.enabled')->label('Usar IA no chat (senão, respostas por regras)'),
                TextInput::make('chat.ollama_model')->label('Modelo local (Ollama)')->placeholder('vazio = padrão do sistema'),
                Textarea::make('chat.instrucoes')->label('Instruções adicionais para a IA')->rows(3)->maxLength(1000)
                    ->helperText('Ex.: tom de voz, termos da empresa, o que priorizar.'),
            ]),
        ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();
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
                ->modalDescription('Remove pesos, limiares, tema e configurações do chat personalizados desta empresa.')->action(fn () => $this->restaurar()),
        ];
    }
}
