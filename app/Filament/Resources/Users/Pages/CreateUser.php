<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // Volta para a listagem ao salvar, como as outras telas do painel.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Mesmo motivo do `EditUser`: `is_admin` e `is_church_president` estão fora
     * do `#[Fillable]`, e o `CreateRecord` do Filament grava por mass
     * assignment. Sem isto, criar um usuário já marcado como presidente o
     * salvava **sem o cargo**, em silêncio — e o acesso ao `/ump` simplesmente
     * não funcionava para ele.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $cargos = [];

        foreach (['is_admin', 'is_church_president'] as $cargo) {
            if (array_key_exists($cargo, $data)) {
                $cargos[$cargo] = (bool) $data[$cargo];
                unset($data[$cargo]);
            }
        }

        $usuario = static::getModel()::create($data);

        if ($cargos !== []) {
            $usuario->forceFill($cargos)->save();
        }

        return $usuario;
    }
}
