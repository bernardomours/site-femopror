<?php

namespace App\Filament\Resources\Boards\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class BoardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('church_id')
                    ->relationship('church', 'name')
                    ->label('Pertence a qual Igreja/Federação?')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),

                Grid::make(2)->schema([
                    TextInput::make('year_start')
                        ->label('Ano de Início')
                        ->numeric()
                        ->required(),
                    TextInput::make('year_end')
                        ->label('Ano de Término')
                        ->numeric()
                        ->required(),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('president_name')
                        ->label('Presidente')
                        ->required(),
                    TextInput::make('vice_president_name')
                        ->label('Vice-Presidente'),
                    TextInput::make('secretary_name')
                        ->label('Secretário(a)'),
                    TextInput::make('treasurer_name')
                        ->label('Tesoureiro(a)'),
                ]),

                FileUpload::make('image_path')
                    ->label('Foto Oficial da Diretoria')
                    ->image()
                    ->directory('boards')
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Diretoria Atual (Ativa)?')
                    ->default(false)
                    ->columnSpanFull(),
            ]);
    }
}