<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Um conjunto de perguntas pronto para aplicar num evento.
 *
 * `fields` tem exatamente a estrutura de `events.custom_fields`: carregar um
 * modelo é copiar o JSON, sem conversão no meio. Um modelo é ponto de partida,
 * não vínculo — mexer nele depois não altera os eventos que já o usaram.
 */
class FormTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'fields'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'fields' => 'array',
        ];
    }

    /** Quantas perguntas o modelo tem — para a listagem não carregar o JSON inteiro à toa. */
    public function quantidadeDePerguntas(): int
    {
        return count($this->fields ?? []);
    }
}
