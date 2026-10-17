<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Converte as opções do formato antigo antes de montar o formulário.
     *
     * As opções eram **uma string** (`Futsal(R$5,00), Vôlei(R$5,00)`) e hoje
     * são uma lista de `{label, surcharge}`. O editor novo usa um repeater, e
     * repeater ligado a uma string quebra a tela — então todo evento anterior
     * ao conversor derrubaria o próprio Edit.
     *
     * Aqui a conversão é só de **formato**: o rótulo vem exatamente como está
     * ("Futsal(R$5,00)"), nunca limpo. Limpar aqui renomearia a opção sem
     * mexer nas respostas já gravadas, e o gráfico do painel, que agrupa pelo
     * texto, racharia em duas colunas. Quem limpa rótulo e resposta junto é o
     * `femopror:migrar-perguntas`, que faz as duas coisas na mesma transação.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($data['custom_fields'] ?? [] as $i => $campo) {
            $data['custom_fields'][$i]['options'] = Event::normalizeOptions($campo['options'] ?? []);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl():string
    {
        return $this->getResource()::getUrl('index');
    }
}
