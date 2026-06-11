<?php

namespace App\Filament\Resources\Churches\Pages;

use App\Filament\Resources\Churches\ChurchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChurch extends CreateRecord
{
    protected static string $resource = ChurchResource::class;

    protected function getRedirectUrl():string
    {
        return $this->getResource()::getUrl('index');
    }
}
