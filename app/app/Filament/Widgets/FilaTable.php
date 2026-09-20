<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Customer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FilaTable extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Fila de atendimento: com quem falar primeiro')
            ->description('Mesma ordem da lista de clientes: quem já está em alerta primeiro e, em cada grupo, atenção × valor do contrato (equilíbrio em Configurações). Ordena a fila; não prevê cancelamento ou perda.')
            ->query(Customer::ordenar(Customer::dashboard()->where('customers.status', 'Ativo'))->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Customer $e) => EmpresaResource::getUrl('view', ['record' => $e]))
            ->columns([
                TextColumn::make('ordem')->label('#')->rowIndex(),
                TextColumn::make('nome')->weight('semibold')->description(fn (Customer $e) => $e->codigo),
                TextColumn::make('nivel')->label('Nível')->badge()
                    ->color(fn (string $state) => ['Crítico' => 'danger', 'Alto' => 'warning', 'Médio' => 'info'][$state] ?? 'success'),
                TextColumn::make('score')->label('Atenção')->tooltip(fn (Customer $e) => $e->resumoScore()),
                TextColumn::make('valor')->label('Contrato/mês')->formatStateUsing(fn ($state) => Customer::brl($state)),
                TextColumn::make('motivo')->label('Por quê')->state(fn (Customer $e) => $e->porQue())->wrap(),
                TextColumn::make('proximo_passo')->label('O que fazer')->state(fn (Customer $e) => $e->proximoPasso())->wrap(),
            ]);
    }
}
