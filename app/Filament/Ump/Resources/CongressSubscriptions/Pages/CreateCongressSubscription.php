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
     * A delegação já abre com o próprio presidente na lista.
     *
     * Ele vai ao congresso como qualquer outro, mas precisava se digitar — e,
     * esquecendo, ficava de fora da delegação que ele mesmo enviou: sumia da
     * contagem da secretaria e do painel.
     *
     * O preenchimento é aqui, e não por `->default()` no repeater, porque
     * repeater com `->relationship()` é hidratado **pela relação**: o default
     * é ignorado e a lista nascia com uma linha em branco.
     *
     * É preenchimento, não imposição — dá para apagar a linha, porque há
     * presidente que organiza sem ir. Forçar no save desfaria a escolha sem
     * avisar. O e-mail é o da conta, e isso basta para o vínculo:
     * `Delegate::booted()` casa delegado com usuário por e-mail.
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $presidente = auth()->user();

        $this->form->fill([
            'delegates' => [[
                'name' => $presidente?->name,
                'email' => $presidente?->email,
                'type' => 'delegado',
            ]],
        ]);

        $this->callHook('afterFill');
    }

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
        // Quem enviou — do servidor, nunca do formulário. A secretaria precisa
        // saber com quem falar sobre uma pendência, e deduzir pelo presidente
        // atual da igreja erraria assim que a presidência trocasse.
        $data['submitted_by'] = auth()->id();
        unset($data['notes']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
