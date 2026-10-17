<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem enviou a inscrição do congresso.
 *
 * A tabela guardava só a **igreja**, então o painel da diretoria não tinha como
 * dizer qual presidente mandou — e a secretaria, ao precisar falar sobre uma
 * pendência, não sabia com quem.
 *
 * Deduzir pelo presidente atual da igreja não serve: a presidência troca, e aí
 * o histórico passaria a atribuir inscrições antigas a quem nem era presidente
 * na época.
 *
 * `nullOnDelete` porque apagar o usuário não pode levar a inscrição da UMP
 * junto — ela é o documento que a secretaria conferiu. Inscrição anterior a
 * esta coluna fica nula, e a tela diz isso em vez de inventar um nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congress_subscriptions', function (Blueprint $table) {
            $table->foreignId('submitted_by')
                ->nullable()
                ->after('church_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('congress_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
        });
    }
};
