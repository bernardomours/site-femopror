<?php

namespace App\Filament\Resources\CongressSubscriptions\Pages;

use App\Filament\Resources\CongressSubscriptions\CongressSubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCongressSubscription extends EditRecord
{
    protected static string $resource = CongressSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //DeleteAction::make(),
        ];
    }
    
}
