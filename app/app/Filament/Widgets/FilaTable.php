<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FilaTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Fila de atendimento: com quem falar primeiro')
            ->description('Ordem por receita em risco (score × valor mensal).')
            ->query(Empresa::query()->where('status', 'Ativo')->orderByDesc('exposicao')->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Empresa $e) => EmpresaResource::getUrl('view', ['record' => $e]))
            ->columns([
                TextColumn::make('nome')->weight('semibold')->description(fn (Empresa $e) => $e->codigo),
                TextColumn::make('nivel')->label('Nível')->badge()
                    ->color(fn (string $state) => ['Crítico' => 'danger', 'Alto' => 'warning', 'Médio' => 'info'][$state] ?? 'success'),
                TextColumn::make('score')->label('Score'),
                TextColumn::make('valor')->label('Contrato/mês')->formatStateUsing(fn ($state) => Empresa::brl($state)),
                TextColumn::make('motivo')->label('Principal motivo')->state(fn (Empresa $e) => $e->sinais[0]['label'] ?? 'Sem sinal forte'),
            ]);
    }
}
