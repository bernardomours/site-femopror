<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As páginas do formulário de inscrição.
 *
 * `[{title, description}]` — a ordem do array é a ordem das páginas, e cada
 * pergunta aponta para um índice daqui por `custom_fields[].page`.
 *
 * Coluna separada, e não um aninhamento dentro de `custom_fields`, por causa
 * do raio de alcance: `custom_fields` é lido pelo formulário público, pelos
 * gráficos do painel, pelos modelos e pelo conversor. Mudar a forma dele para
 * `[{página, perguntas[]}]` obrigaria a mexer nos cinco e a migrar o que já
 * está gravado. Assim, **nulo significa página única** e todo evento existente
 * segue funcionando sem conversão nenhuma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('form_pages')->nullable()->after('custom_fields');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('form_pages');
        });
    }
};
