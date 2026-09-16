<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Mail\InscricaoConfirmada;
use App\Support\SafeMail;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // A listagem lia event e church linha a linha (N+1).
            ->modifyQueryUsing(fn ($query) => $query->with(['event', 'church']))
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

                TextColumn::make('amount_paid')
                    ->label('Valor')
                    ->money('BRL')
                    ->placeholder('—')
                    ->sortable()
                    ->description('cobrado na inscrição'),

                TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    // O match não tinha `default`: um status fora dos três
                    // derrubava a tabela inteira com UnhandledMatchError.
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'failed' => 'Cancelado',
                        default => (string) $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Inscrito em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('ver_comprovante')
                    ->label('Ver PIX')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    // Era asset('storage/...'), um endereço público e permanente
                    // para um comprovante bancário. Agora é um link assinado que
                    // vale 30 minutos.
                    ->url(fn ($record) => Storage::disk(config('femopror.uploads.disk'))->temporaryUrl($record->receipt_path, now()->addMinutes(30)))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => filled($record->receipt_path)),

                Action::make('aprovar_pagamento')
                    ->label('Confirmar Pagamento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Recebimento do PIX')
                    ->modalDescription(fn ($record) => $record->amount_paid
                        ? 'O sistema cobrou R$ '.number_format((float) $record->amount_paid, 2, ',', '.').' desta pessoa. Confira se o valor bate com o comprovante.'
                        : 'Tem certeza de que o valor já consta na conta da federação?')
                    ->modalSubmitActionLabel('Sim, valor recebido')
                    ->visible(fn ($record) => $record->payment_status === 'pending')
                    ->action(function ($record) {
                        $record->update(['payment_status' => 'paid']);

                        // É este o e-mail que a pessoa espera: "sua vaga está
                        // garantida". Se o envio falhar, a aprovação continua
                        // valendo — a tesouraria é avisada para não achar que
                        // a pessoa foi notificada.
                        $enviado = SafeMail::send($record->email, new InscricaoConfirmada($record));

                        Notification::make()
                            ->title('Pagamento aprovado!')
                            ->body($enviado
                                ? 'Avisamos '.$record->email.' por e-mail.'
                                : 'Atenção: o e-mail de confirmação não saiu. Avise a pessoa por outro canal.')
                            ->color($enviado ? 'success' : 'warning')
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
