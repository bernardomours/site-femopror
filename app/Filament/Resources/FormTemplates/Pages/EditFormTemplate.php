<?php

namespace App\Filament\Resources\FormTemplates\Pages;

use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Models\Event;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFormTemplate extends EditRecord
{
    protected static string $resource = FormTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Mesmo motivo do EditEvent: o repeater de opções quebra se o modelo tiver
     * sido gravado com as opções em string (formato antigo).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($data['fields'] ?? [] as $i => $campo) {
            $data['fields'][$i]['options'] = Event::normalizeOptions($campo['options'] ?? []);
        }

        return $data;
    }
}
