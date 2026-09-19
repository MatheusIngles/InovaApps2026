<?php

namespace App\Filament\Resources\Empresas;

use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Empresas\Pages\ViewEmpresa;
use App\Models\Customer;
use App\Support\Tenancy\CompanyContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmpresaResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?int $navigationSort = 2;

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
            ->recordClasses(fn (Customer $e) => 'nv-'.['Crítico' => 'crit', 'Alto' => 'alto', 'Médio' => 'med', 'Baixo' => 'baixo', 'Cancelada' => 'canc'][$e->rotulo()])
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
                            ->formatStateUsing(fn ($state) => "Score {$state}/100"),
                        TextColumn::make('valor')->alignEnd()
                            ->state(fn (Customer $e) => $e->cancelada() ? "Cancelou em {$e->mes_cancel}" : Customer::brl($e->valor).'/mês'),
                    ]),
                    TextColumn::make('sinais')->color('gray')->size(TextSize::Small)->limit(70)
                        ->state(fn (Customer $e) => $e->sinais[0]['texto'] ?? 'Sem sinais relevantes')
                        ->searchable(['segment', 'external_code']),
                ])->space(3),
            ])
            ->filters([
                SelectFilter::make('nivel')->label('Nível')
                    ->options(['Crítico' => 'Crítico', 'Alto' => 'Alto', 'Médio' => 'Médio', 'Baixo' => 'Baixo', 'Cancelada' => 'Cancelada'])
                    ->query(function (Builder $query, array $data) {
                        $l = app(CompanyContext::class)->current()->limiares(); // limiares da empresa

                        return match ($data['value'] ?? null) {
                            null, '' => $query,
                            'Cancelada' => $query->where('customers.status', 'Cancelado'),
                            'Crítico' => $query->where('customers.status', 'Ativo')->where('assessment.health_score', '>=', $l['critico']),
                            'Alto' => $query->where('customers.status', 'Ativo')->where('assessment.health_score', '>=', $l['alto'])->where('assessment.health_score', '<', $l['critico']),
                            'Médio' => $query->where('customers.status', 'Ativo')->where('assessment.health_score', '>=', $l['medio'])->where('assessment.health_score', '<', $l['alto']),
                            default => $query->where('customers.status', 'Ativo')->where('assessment.health_score', '<', $l['medio']),
                        };
                    }),
                SelectFilter::make('segment')->label('Segmento')->options(fn () => Customer::distinct()->orderBy('segment')->pluck('segment', 'segment')->all()),
            ])
            ->recordActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmpresas::route('/'),
            'view' => ViewEmpresa::route('/{record}'),
        ];
    }
}
