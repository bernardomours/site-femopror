<?php

namespace App\Filament\Resources\CongressSubscriptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class CongressSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('church.name')
                    ->label('UMP Local')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('delegates_count')
                    ->counts('delegates')
                    ->label('Qtd. Delegados')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('event.title')
                    ->label('Congresso')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendente' => 'warning',
                        'aprovado' => 'success',
                        'recusado' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Recebido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
