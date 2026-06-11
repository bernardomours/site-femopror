<?php

namespace App\Filament\Resources\CongressSubscriptions;

use App\Filament\Resources\CongressSubscriptions\Pages\CreateCongressSubscription;
use App\Filament\Resources\CongressSubscriptions\Pages\EditCongressSubscription;
use App\Filament\Resources\CongressSubscriptions\Pages\ListCongressSubscriptions;
use App\Filament\Resources\CongressSubscriptions\Schemas\CongressSubscriptionForm;
use App\Filament\Resources\CongressSubscriptions\Tables\CongressSubscriptionsTable;
use App\Models\CongressSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use UnitEnum;
use Filament\Tables\Table;

class CongressSubscriptionResource extends Resource
{
    protected static ?string $model = CongressSubscription::class;

    protected static ?string $modelLabel = 'Inscrição - Congresso';
    protected static ?string $pluralModelLabel = 'Inscrição - Congresso';
    protected static ?string $navigationLabel = 'Inscrição - Congresso';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|UnitEnum|null $navigationGroup = 'Inscrições';

    public static function form(Schema $schema): Schema
    {
        return CongressSubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CongressSubscriptionsTable::configure($table);
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
            'index' => ListCongressSubscriptions::route('/'),
            'create' => CreateCongressSubscription::route('/create'),
            'edit' => EditCongressSubscription::route('/{record}/edit'),
        ];
    }
}
