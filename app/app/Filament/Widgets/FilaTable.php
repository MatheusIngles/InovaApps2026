<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Customer;
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
            ->description('Mesma ordem da lista de clientes: risco × valor do contrato. O equilíbrio é ajustável em Configurações.')
            ->query(Customer::ordenar(Customer::dashboard()->where('customers.status', 'Ativo'))->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Customer $e) => EmpresaResource::getUrl('view', ['record' => $e]))
            ->columns([
                TextColumn::make('nome')->weight('semibold')->description(fn (Customer $e) => $e->codigo),
                TextColumn::make('nivel')->label('Nível')->badge()
                    ->color(fn (string $state) => ['Crítico' => 'danger', 'Alto' => 'warning', 'Médio' => 'info'][$state] ?? 'success'),
                TextColumn::make('score')->label('Score'),
                TextColumn::make('valor')->label('Contrato/mês')->formatStateUsing(fn ($state) => Customer::brl($state)),
                TextColumn::make('motivo')->label('Principal motivo')->state(fn (Customer $e) => $e->sinais[0]['label'] ?? 'Sem sinal forte'),
            ]);
    }
}
