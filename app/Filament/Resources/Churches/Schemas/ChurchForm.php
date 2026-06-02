<?php

namespace App\Filament\Resources\Churches\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChurchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Igreja')
                    ->placeholder('Igreja Presbiteriana Central de Mossoró')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->required(),
                Toggle::make('is_federation')
                    ->label('Faz parte da feredação?')
                    ->required()
                    ->default(false),
            ]);
    }
}
