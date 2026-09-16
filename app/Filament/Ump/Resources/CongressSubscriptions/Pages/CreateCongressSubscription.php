<?php

namespace App\Filament\Ump\Resources\CongressSubscriptions\Pages;

use App\Filament\Ump\Resources\CongressSubscriptions\CongressSubscriptionResource;
use App\Models\CongressSubscription;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateCongressSubscription extends CreateRecord
{
    protected static string $resource = CongressSubscriptionResource::class;

    /**
     * A igreja e a situação são resolvidas aqui, no servidor, a partir do
     * usuário autenticado — nunca do que veio no formulário. Ver a nota no
     * CongressSubscriptionForm sobre por que os `Hidden` saíram.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $churchId = auth()->user()->church_id;

        abort_if($churchId === null, 403, 'Seu usuário não está vinculado a nenhuma UMP.');

        // Uma UMP manda uma inscrição por congresso. A trava fica aqui porque o
        // índice único no banco exigiria apagar duplicatas existentes, e apagar
        // uma inscrição leva junto delegados e documentos em cascata.
        $jaExiste = CongressSubscription::where('church_id', $churchId)
            ->where('event_id', $data['event_id'] ?? null)
            ->exists();

        if ($jaExiste) {
            Notification::make()
                ->title('Sua UMP já enviou uma inscrição para este congresso')
                ->body('Abra a inscrição existente para corrigir ou completar os dados.')
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.event_id' => 'Já existe uma inscrição da sua UMP para este congresso.',
            ]);
        }

        $data['church_id'] = $churchId;
        $data['status'] = 'pendente';
        unset($data['notes']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
