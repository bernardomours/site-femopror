<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions\Pages;

use App\Filament\Ump\Resources\CongressSubscriptions\CongressSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCongressSubscriptions extends ListRecords
{
    protected static string $resource = CongressSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
