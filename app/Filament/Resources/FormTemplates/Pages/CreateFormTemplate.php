<?php

namespace App\Filament\Resources\FormTemplates\Pages;

use App\Filament\Resources\FormTemplates\FormTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFormTemplate extends CreateRecord
{
    protected static string $resource = FormTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
