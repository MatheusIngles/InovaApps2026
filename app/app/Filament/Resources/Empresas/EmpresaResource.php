<?php

namespace App\Filament\Resources\Empresas;

use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Empresas\Pages\ViewEmpresa;
use App\Models\Empresa;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
    protected static ?string $model = Empresa::class;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

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
            ->modifyQueryUsing(fn (Builder $query) => Empresa::ordenar($query))
            ->defaultSort(null)
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated([12, 24, 48, 'all'])
            ->defaultPaginationPageOption(24)
            ->searchPlaceholder('Buscar por nome, código ou segmento…')
            ->recordClasses(fn (Empresa $e) => $e->cancelada() ? 'opacity-60' : null)
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('nome')->weight(FontWeight::SemiBold)->searchable()
                            ->description(fn (Empresa $e) => "{$e->codigo} · {$e->segmento} · {$e->porte}"),
                        TextColumn::make('nivel')->badge()->grow(false)
                            ->state(fn (Empresa $e) => $e->rotulo())->color(fn (string $state) => self::cor($state)),
                    ]),
                    Split::make([
                        TextColumn::make('score')->size(TextSize::Large)->weight(FontWeight::Bold)
                            ->formatStateUsing(fn ($state) => "Risco {$state}/100"),
                        TextColumn::make('valor')->alignEnd()
                            ->state(fn (Empresa $e) => $e->cancelada() ? "Cancelou em {$e->mes_cancel}" : Empresa::brl($e->valor).'/mês'),
                    ]),
                    TextColumn::make('sinais')->color('gray')->size(TextSize::Small)->limit(70)
                        ->state(fn (Empresa $e) => $e->sinais[0]['texto'] ?? 'Sem sinais relevantes')
                        ->searchable(['segmento', 'codigo']),
                ])->space(3),
            ])
            ->filters([
                SelectFilter::make('nivel')->label('Nível')
                    ->options(['Crítico' => 'Crítico', 'Alto' => 'Alto', 'Médio' => 'Médio', 'Baixo' => 'Baixo', 'Cancelada' => 'Cancelada'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, "" => $query,
                        'Cancelada' => $query->where('status', 'Cancelado'),
                        default => $query->where('status', 'Ativo')->where('nivel', $data['value']),
                    }),
                SelectFilter::make('segmento')->options(fn () => Empresa::distinct()->orderBy('segmento')->pluck('segmento', 'segmento')->all()),
            ])
            ->recordActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(['default' => 2, 'md' => 3, 'xl' => 6])->schema([
                TextEntry::make('nivel')->label('Nível')->badge()
                    ->state(fn (Empresa $e) => $e->rotulo())->color(fn (string $state) => self::cor($state)),
                TextEntry::make('score')->label('Score de risco')->size(TextSize::Large)->weight(FontWeight::Bold)
                    ->formatStateUsing(fn ($state) => "{$state} / 100"),
                TextEntry::make('valor')->label('Contrato/mês')->formatStateUsing(fn ($state) => Empresa::brl($state)),
                TextEntry::make('exposicao')->label('Receita em risco')->formatStateUsing(fn ($state) => Empresa::brl($state)),
                TextEntry::make('plano'),
                TextEntry::make('sla_h')->label('SLA contratado')->suffix(' h'),
            ]),
            Section::make('Por que está neste nível e o que fazer')
                ->description('Sinais dos últimos 3 meses, do mais para o menos relevante.')
                ->schema([
                    RepeatableEntry::make('sinais')->hiddenLabel()->contained(false)->schema([
                        TextEntry::make('label')->hiddenLabel()->weight(FontWeight::SemiBold),
                        TextEntry::make('texto')->hiddenLabel()->color('gray'),
                        TextEntry::make('acao')->hiddenLabel()->icon(Heroicon::OutlinedArrowRightCircle)->iconColor('primary'),
                    ])->columns(['md' => 3]),
                ]),
            Section::make('Empresas que cancelaram em estado similar')
                ->description('Comparação do perfil dos últimos 3 meses com o de quem já saiu.')
                ->schema([
                    RepeatableEntry::make('similares')->hiddenLabel()->contained(false)->grid(['md' => 3])->schema([
                        TextEntry::make('nome')->hiddenLabel()->weight(FontWeight::SemiBold)
                            ->url(fn (string $state) => static::getUrl('view', ['record' => Empresa::where('nome', $state)->value('codigo')])),
                        TextEntry::make('mes_cancel')->hiddenLabel()->prefix('Cancelou em '),
                        TextEntry::make('sim')->hiddenLabel()->badge()->color('danger')->suffix('% de semelhança'),
                    ]),
                ]),
            Section::make('Pesquisas de satisfação (NPS)')->collapsible()->schema([
                RepeatableEntry::make('nps')->hiddenLabel()->contained(false)->grid(['default' => 3, 'md' => 6, 'xl' => 9])->schema([
                    TextEntry::make('mes')->hiddenLabel()->size(TextSize::ExtraSmall)->color('gray'),
                    TextEntry::make('nota')->hiddenLabel()->size(TextSize::Large)->weight(FontWeight::Bold),
                ]),
            ]),
            Section::make('Histórico mensal')->collapsible()->schema([
                RepeatableEntry::make('hist')->hiddenLabel()->table([
                    TableColumn::make('Mês'), TableColumn::make('Chamados'), TableColumn::make('Reabertos'), TableColumn::make('SLA %'),
                    TableColumn::make('Uso %'), TableColumn::make('Reclam.'), TableColumn::make('Atraso (d)'), TableColumn::make('Reuniões'),
                ])->schema([
                    TextEntry::make('mes'), TextEntry::make('abertos'), TextEntry::make('reabertos'), TextEntry::make('sla'),
                    TextEntry::make('uso'), TextEntry::make('recl'), TextEntry::make('atraso'), TextEntry::make('reunioes'),
                ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmpresas::route('/'),
            'view' => ViewEmpresa::route('/{record}'),
        ];
    }
}
