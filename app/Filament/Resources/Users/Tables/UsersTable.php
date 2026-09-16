<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('church'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),

                // Era `church_id` como número cru — o admin via "3" e tinha de
                // adivinhar de que igreja se tratava.
                TextColumn::make('church.name')
                    ->label('Igreja / UMP')
                    ->placeholder('Sem vínculo')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_church_president')
                    ->label('Presidente UMP')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_admin')
                    ->label('Administrador')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Administradores')
                    ->placeholder('Todos')
                    ->trueLabel('Somente administradores')
                    ->falseLabel('Somente usuários comuns'),

                TernaryFilter::make('church_id')
                    ->label('Vínculo com UMP')
                    ->placeholder('Todos')
                    ->trueLabel('Com igreja')
                    ->falseLabel('Sem igreja')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('church_id'),
                        false: fn ($query) => $query->whereNull('church_id'),
                    ),
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
