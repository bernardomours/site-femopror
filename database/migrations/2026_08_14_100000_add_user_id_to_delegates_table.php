<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O delegado era ligado ao usuário só pelo e-mail digitado pelo presidente
     * da UMP. Se a pessoa trocasse o e-mail no perfil, perdia a inscrição de
     * vista. Agora o vínculo é por FK; o e-mail continua guardado porque nem
     * todo delegado tem conta no momento em que a UMP envia a lista.
     */
    public function up(): void
    {
        Schema::table('delegates', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('congress_subscription_id')
                ->constrained()->nullOnDelete();

            // O dashboard procura o delegado por e-mail em toda visita.
            $table->index('email');
        });

        // Amarra quem já tem conta com o mesmo e-mail.
        DB::table('delegates')
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->orderBy('id')
            ->each(function ($delegate) {
                $userId = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($delegate->email)])
                    ->value('id');

                if ($userId) {
                    DB::table('delegates')->where('id', $delegate->id)->update(['user_id' => $userId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('delegates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropIndex(['email']);
        });
    }
};
