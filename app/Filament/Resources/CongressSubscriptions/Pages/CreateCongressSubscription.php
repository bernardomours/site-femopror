<?php

namespace App\Filament\Resources\CongressSubscriptions\Pages;

use App\Filament\Resources\CongressSubscriptions\CongressSubscriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCongressSubscription extends CreateRecord
{
    protected static string $resource = CongressSubscriptionResource::class;

    protected function getRedirectUrl():string
    {
        return $this->getResource()::getUrl('index');
    }
}
