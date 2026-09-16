<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CongressSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.title')
                    ->label('Congresso')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('delegates_count')
                    ->counts('delegates')
                    ->label('Delegação')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Status da Inscrição')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pendente' => 'warning',
                        'aprovado' => 'success',
                        'recusado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pendente' => 'Em análise',
                        'aprovado' => 'Aprovada',
                        'recusado' => 'Com pendências',
                        default => (string) $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Enviada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            // Sem exclusão em massa: a UMP apagaria a própria inscrição junto
            // com delegados e documentos em cascata. O DeleteAction individual
            // já estava comentado — a bulk tinha ficado para trás.
            ->toolbarActions([]);
    }
}
