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
                    TextInput::make('president_name')
                        ->label('Presidente')
                        ->required(),
                    TextInput::make('vice_president_name')
                        ->label('Vice-Presidente'),
                    TextInput::make('first_secretary_name')
                        ->label('1º Secretário(a)'),
                    TextInput::make('second_secretary_name')
                        ->label('2º Secretário(a)'),
                    TextInput::make('executive_secretary_name')
                        ->label('Secretário(a) Executivo(a)'),
                    TextInput::make('treasurer_name')
                        ->label('Tesoureiro(a)'),
                ]),

                FileUpload::make('image_path')
                    ->label('Foto Oficial da Diretoria')
                    ->image()
                    ->disk('public')
                    ->directory('boards'),

                Toggle::make('is_active')
                    ->label('Diretoria Atual (Ativa)?')
                    ->default(false)
                    ->columnSpanFull(),
            ]);
    }
}