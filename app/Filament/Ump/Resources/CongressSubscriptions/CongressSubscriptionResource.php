<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions;

use App\Filament\Ump\Resources\CongressSubscriptions\Pages\CreateCongressSubscription;
use App\Filament\Ump\Resources\CongressSubscriptions\Pages\EditCongressSubscription;
use App\Filament\Ump\Resources\CongressSubscriptions\Pages\ListCongressSubscriptions;
use App\Filament\Ump\Resources\CongressSubscriptions\Schemas\CongressSubscriptionForm;
use App\Filament\Ump\Resources\CongressSubscriptions\Tables\CongressSubscriptionsTable;
use App\Models\CongressSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CongressSubscriptionResource extends Resource
{
    protected static ?string $model = CongressSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $modelLabel = 'Inscrição - Congresso';
    protected static ?string $pluralModelLabel = 'Inscrição - Congresso';
    protected static ?string $navigationLabel = 'Inscrição - Congresso';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('church_id', data_get(auth()->user(), 'church_id'));
    }
}
