<?php

namespace App\Filament\Resources\Empresas;

use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Empresas\Pages\ViewEmpresa;
use App\Models\Customer;
use App\Support\Tenancy\CompanyContext;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmpresaResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getEloquentQuery(): Builder
    {
        return Customer::dashboard();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function cor(string $rotulo): string
    {
        return ['Crítico' => 'danger', 'Alto' => 'warning', 'Médio' => 'info', 'Baixo' => 'success'][$rotulo] ?? 'gray';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => Customer::ordenar($query))
            ->defaultSort(null)
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated([12, 24, 48, 'all'])
            ->defaultPaginationPageOption(24)
            ->searchPlaceholder('Buscar por nome, código ou segmento…')
            ->recordClasses(fn (Customer $e) => 'nv-'.['Crítico' => 'crit', 'Alto' => 'alto', 'Médio' => 'med', 'Baixo' => 'baixo', 'Cancelado' => 'canc'][$e->rotulo()])
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('nome')->weight(FontWeight::SemiBold)->searchable(['external_code', 'segment'])
                            ->description(fn (Customer $e) => "{$e->codigo} · {$e->segmento} · {$e->porte}"),
                        TextColumn::make('nivel')->badge()->grow(false)
                            ->state(fn (Customer $e) => $e->rotulo())->color(fn (string $state) => self::cor($state)),
                    ]),
                    Split::make([
                        TextColumn::make('score')->size(TextSize::Large)->weight(FontWeight::Bold)
                            ->formatStateUsing(fn ($state) => "Risco {$state}%")
                            ->description('8 sinais com pesos ajustáveis; veja as parcelas no cliente.')
                            ->tooltip(fn (Customer $e) => $e->resumoScore()),
                        TextColumn::make('valor')->alignEnd()
                            ->state(fn (Customer $e) => $e->cancelada() ? "Cancelou em {$e->mes_cancel}" : Customer::brl($e->valor).'/mês'),
                    ]),
                    TextColumn::make('sinais')->color('gray')->size(TextSize::Small)->limit(70)
                        ->state(fn (Customer $e) => $e->sinais[0]['texto'] ?? 'Sem sinais relevantes')
                        ->searchable(['segment', 'external_code']),
                ])->space(3),
            ])
            ->filters([
                SelectFilter::make('nivel')->label('Nível de risco')->multiple()
                    ->options(['Crítico' => 'Crítico', 'Alto' => 'Alto', 'Médio' => 'Médio', 'Baixo' => 'Baixo', 'Cancelado' => 'Cancelado'])
                    ->query(function (Builder $query, array $data) {
                        $niveis = array_filter($data['values'] ?? []);
                        $l = app(CompanyContext::class)->current()->limiares(); // limiares da empresa

                        return $query->when($niveis, fn (Builder $query) => $query->where(function (Builder $query) use ($niveis, $l) {
                            foreach ($niveis as $nivel) {
                                $query->orWhere(fn (Builder $q) => match ($nivel) {
                                    'Cancelado' => $q->where('customers.status', 'Cancelado'),
                                    'Crítico' => $q->where('customers.status', 'Ativo')->where('assessment.health_score', '>=', $l['critico']),
                                    'Alto' => $q->where('customers.status', 'Ativo')->where('assessment.health_score', '>=', $l['alto'])->where('assessment.health_score', '<', $l['critico']),
                                    'Médio' => $q->where('customers.status', 'Ativo')->where('assessment.health_score', '>=', $l['medio'])->where('assessment.health_score', '<', $l['alto']),
                                    default => $q->where('customers.status', 'Ativo')->where('assessment.health_score', '<', $l['medio']),
                                });
                            }
                        }));
                    }),
                SelectFilter::make('segment')->label('Segmento')->options(fn () => Customer::distinct()->orderBy('segment')->pluck('segment', 'segment')->all()),
                SelectFilter::make('status')->label('Situação')
                    ->options(['Ativo' => 'Ativo', 'Cancelado' => 'Cancelado'])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query) => $query->where('customers.status', $data['value'])
                    )),
                SelectFilter::make('size')->label('Porte')
                    ->options(fn () => Customer::distinct()->orderBy('size')->pluck('size', 'size')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query) => $query->where('customers.size', $data['value'])
                    )),
                SelectFilter::make('plan')->label('Plano')
                    ->options(fn () => Customer::distinct()->orderBy('plan')->pluck('plan', 'plan')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query) => $query->where('customers.plan', $data['value'])
                    )),
                Filter::make('score_range')->label('Faixa de risco')
                    ->indicateUsing(fn (array $data) => self::indicadorFaixa('Risco', $data))
                    ->schema([
                        TextInput::make('min')->label('Risco mínimo (%)')->numeric()->minValue(0)->maxValue(100),
                        TextInput::make('max')->label('Risco máximo (%)')->numeric()->minValue(0)->maxValue(100),
                    ])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $query) => $query->where('assessment.health_score', '>=', $data['min']))
                        ->when(filled($data['max'] ?? null), fn (Builder $query) => $query->where('assessment.health_score', '<=', $data['max']))),
                Filter::make('monthly_value_range')->label('Valor mensal')
                    ->indicateUsing(fn (array $data) => self::indicadorFaixa('Valor', $data, 'R$ '))
                    ->schema([
                        TextInput::make('min')->label('Valor mínimo (R$)')->numeric()->minValue(0),
                        TextInput::make('max')->label('Valor máximo (R$)')->numeric()->minValue(0),
                    ])->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $query) => $query->where('customers.monthly_value', '>=', $data['min']))
                        ->when(filled($data['max'] ?? null), fn (Builder $query) => $query->where('customers.monthly_value', '<=', $data['max']))),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible) // filtros lado a lado, escondidos até clicar no botão de filtro
            ->filtersFormColumns(3)
            ->deferFilters(false) // aplica assim que o filtro muda, sem botão
            ->recordActions([]);
    }

    /** Texto do chip de filtro ativo para faixas (mínimo/máximo). */
    private static function indicadorFaixa(string $nome, array $data, string $prefixo = ''): ?string
    {
        $min = $data['min'] ?? null;
        $max = $data['max'] ?? null;

        return match (true) {
            filled($min) && filled($max) => "$nome: $prefixo$min a $prefixo$max",
            filled($min) => "$nome ≥ $prefixo$min",
            filled($max) => "$nome ≤ $prefixo$max",
            default => null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmpresas::route('/'),
            'view' => ViewEmpresa::route('/{record}'),
        ];
    }
}
