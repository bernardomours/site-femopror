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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Escopo da igreja do usuário. É por aqui que o Filament também resolve o
     * `{record}` da tela de edição, então a URL direta de outra UMP dá 404.
     *
     * Antes era `where('church_id', $user->church_id)` direto: para um admin
     * (church_id nulo) isso virava `where church_id is null` e a tela ficava
     * sempre vazia, sem explicação.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return $query;
        }

        return $query->where('church_id', $user?->church_id ?? 0);
    }

    /**
     * Só o presidente da UMP envia inscrição. Ter igreja não basta — todo jovem
     * escolhe a própria igreja no perfil. Admin acompanha pelo painel da
     * diretoria; aqui ele só observa.
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->isChurchPresident() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record->isEditableByChurch() && (auth()->user()?->isChurchPresident() ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
