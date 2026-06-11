<?php

namespace App\Filament\Resources\Registrations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Participante')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('event.title')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('church.name')
                    ->label('Igreja')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                    
                TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'failed' => 'Cancelado',
                    }),
                    
                TextColumn::make('created_at')
                    ->label('Inscrito em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->relationship('event', 'title')
                    ->label('Filtrar por Evento'),
                    
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'failed' => 'Cancelado',
                    ])
                    ->label('Filtrar por Status'),
            ],layout: FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('ver_comprovante')
                ->label('Ver PIX')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn ($record) => asset('storage/' . $record->receipt_path))
                ->openUrlInNewTab()
                ->visible(fn ($record) => $record->receipt_path !== null),

                Action::make('aprovar_pagamento')
                    ->label('Confirmar Pagamento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Recebimento do PIX')
                    ->modalDescription('Tem certeza de que o valor já consta na conta da federação?')
                    ->modalSubmitActionLabel('Sim, valor recebido')
                    ->visible(fn ($record) => $record->payment_status === 'pending')
                    ->action(function ($record) {
                        $record->update(['payment_status' => 'paid']);
                        
                        Notification::make()
                            ->title('Pagamento Aprovado!')
                            ->success()
                            ->send();
                    }),
                    
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
