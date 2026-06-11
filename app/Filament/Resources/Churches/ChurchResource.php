<?php

namespace App\Filament\Resources\Churches;

use App\Filament\Resources\Churches\Pages\CreateChurch;
use App\Filament\Resources\Churches\Pages\EditChurch;
use App\Filament\Resources\Churches\Pages\ListChurches;
use App\Filament\Resources\Churches\Schemas\ChurchForm;
use App\Filament\Resources\Churches\Tables\ChurchesTable;
use App\Models\Church;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChurchResource extends Resource
{
    protected static ?string $model = Church::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $modelLabel = 'Igreja';
    protected static ?string $pluralModelLabel = 'Igrejas';
    protected static ?string $navigationLabel = 'Igrejas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ChurchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChurchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChurches::route('/'),
            'create' => CreateChurch::route('/create'),
            'edit' => EditChurch::route('/{record}/edit'),
        ];
    }
}
