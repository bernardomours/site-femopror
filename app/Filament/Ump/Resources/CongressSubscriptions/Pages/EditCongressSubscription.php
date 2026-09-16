<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions\Pages;

use App\Filament\Ump\Resources\CongressSubscriptions\CongressSubscriptionResource;
use Filament\Resources\Pages\EditRecord;

class EditCongressSubscription extends EditRecord
{
    protected static string $resource = CongressSubscriptionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Inscrição aprovada é o documento que a secretaria conferiu. Trocar
        // delegado ou comprovante depois disso reescreveria o que já foi
        // validado — a UMP precisa pedir a reabertura.
        abort_unless($this->getRecord()->isEditableByChurch(), 403, 'Esta inscrição já foi aprovada e não pode mais ser alterada.');
    }

    /**
     * Mesma razão do create: nem a igreja nem a situação podem vir do cliente.
     * Sem isto, uma request forjada no Edit trocaria `status` para `aprovado`
     * mesmo com os campos fora do formulário.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['church_id'], $data['status'], $data['notes']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
