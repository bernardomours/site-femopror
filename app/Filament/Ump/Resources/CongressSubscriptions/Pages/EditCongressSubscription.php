<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions\Pages;

use App\Filament\Ump\Resources\CongressSubscriptions\CongressSubscriptionResource;
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

    protected function getRedirectUrl():string
    {
        return $this->getResource()::getUrl('index');
    }
}
