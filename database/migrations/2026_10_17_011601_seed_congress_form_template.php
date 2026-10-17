<?php

use App\Models\FormTemplate;
use App\Support\PerguntasPadraoDoCongresso;
use Illuminate\Database\Migrations\Migration;

/**
 * O conjunto do congresso deixa de ser código e vira dado.
 *
 * Ele nasceu como classe PHP (`PerguntasPadraoDoCongresso`), o que significava
 * deploy para trocar uma palavra de uma pergunta. Agora é o primeiro registro
 * de `form_templates`, e a diretoria edita sozinha.
 *
 * A classe continua existindo como a **origem** desta semente, para que o
 * conteúdo tenha um lugar versionado — mas quem manda, a partir daqui, é a
 * linha no banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        FormTemplate::updateOrCreate(
            ['name' => 'Congresso — padrão'],
            [
                'description' => 'Categoria do participante e restrição alimentar. Nome, e-mail, WhatsApp e igreja já são perguntados automaticamente.',
                'fields' => PerguntasPadraoDoCongresso::campos(),
            ],
        );
    }

    public function down(): void
    {
        FormTemplate::where('name', 'Congresso — padrão')->delete();
    }
};
