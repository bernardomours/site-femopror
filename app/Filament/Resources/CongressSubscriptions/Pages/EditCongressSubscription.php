<?php

namespace App\Filament\Resources\CongressSubscriptions\Pages;

use App\Filament\Resources\CongressSubscriptions\CongressSubscriptionResource;
use Filament\Resources\Pages\EditRecord;

class EditCongressSubscription extends EditRecord
{
    protected static string $resource = CongressSubscriptionResource::class;

    /**
     * Sem exclusão de propósito: apagar a inscrição leva delegados e
     * documentos em cascata, e ela é o documento que a secretaria conferiu.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    // Volta para a listagem ao salvar, como as outras telas do painel: depois
    // de avaliar uma inscrição, o passo seguinte é a próxima da fila.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
