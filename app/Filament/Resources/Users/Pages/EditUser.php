<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    // Volta para a listagem ao salvar, como as outras telas do painel.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Grava as duas colunas de cargo fora do mass assignment.
     *
     * `is_admin` e `is_church_president` estão **fora do `#[Fillable]`** de
     * propósito: são as colunas que decidem acesso a painel, e nenhum caminho
     * genérico deve escrevê-las. Só que o `EditRecord` do Filament grava
     * justamente por `$record->update($data)` — ou seja, por mass assignment.
     *
     * O resultado era o pior tipo de falha: marcar "presidente", salvar, ver a
     * notificação de sucesso e **nada mudar**. Sem erro, sem aviso; o Eloquent
     * descarta atributo não-fillable em silêncio.
     *
     * Aqui a atribuição é direta, e continua sendo este Resource — que já exige
     * um admin autenticado — o único lugar que escreve essas colunas.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        foreach (['is_admin', 'is_church_president'] as $cargo) {
            if (array_key_exists($cargo, $data)) {
                $record->{$cargo} = (bool) $data[$cargo];
                unset($data[$cargo]);
            }
        }

        $record->fill($data);
        $record->save();

        return $record;
    }
}
