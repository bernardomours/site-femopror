<?php

namespace App\Filament\Resources\Boards\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class BoardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Foto')
                    ->circular(),
                    
                TextColumn::make('church.name')
                    ->label('Igreja/Federação')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('president_name')
                    ->label('Presidente')
                    ->searchable(),
                    
                TextColumn::make('year_start')
                    ->label('Gestão')
                    ->formatStateUsing(fn ($record) => $record->year_start . ' - ' . $record->year_end)
                    ->sortable(),
                    
                IconColumn::make('is_active')
                    ->label('Ativa?')
                    ->boolean(),
            ])
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
