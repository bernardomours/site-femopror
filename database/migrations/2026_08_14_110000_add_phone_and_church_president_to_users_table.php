<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Igreja e telefone passam a morar na conta, para o formulário de inscrição
     * não pedir os mesmos dados toda vez. Isso obriga a separar duas coisas que
     * até aqui eram a mesma coluna:
     *
     *   - `church_id`          = a igreja de que a pessoa faz parte (ela escolhe)
     *   - `is_church_president` = é o presidente da UMP (a diretoria define)
     *
     * Sem essa separação, deixar o usuário escolher a própria igreja em
     * /profile daria acesso ao painel /ump para qualquer um — e de lá dá para
     * enviar a inscrição do congresso em nome daquela igreja.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_church_president')->default(false)->after('is_admin');
        });

        // Quem já tinha igreja e não é admin era, na prática, presidente local:
        // preserva o acesso que essas contas já tinham.
        DB::table('users')
            ->whereNotNull('church_id')
            ->where('is_admin', false)
            ->update(['is_church_president' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'is_church_president']);
        });
    }
};
