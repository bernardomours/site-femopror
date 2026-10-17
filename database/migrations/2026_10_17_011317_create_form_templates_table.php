<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conjuntos de perguntas reutilizáveis entre eventos.
 *
 * O conjunto do congresso vivia numa classe PHP, o que significa que mudar uma
 * pergunta exigia deploy. Aqui a diretoria cria e edita os modelos sozinha.
 *
 * `fields` guarda a mesma estrutura de `events.custom_fields`, de propósito:
 * carregar um modelo é copiar o JSON para o evento, sem conversão no meio. Um
 * modelo é um ponto de partida, não um vínculo — editar o modelo depois **não**
 * mexe nos eventos que já o usaram, e é isso que se quer: evento que já recebeu
 * inscrição não pode mudar de pergunta pelas costas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->json('fields');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_templates');
    }
};
