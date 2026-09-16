<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "É congresso?" era decidido por str_contains no título. Renomear o evento
     * quebrava o fluxo de delegado/visitante e a contagem do painel.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_congress')->default(false)->after('status');
        });

        // Preserva o comportamento atual dos eventos já cadastrados.
        DB::table('events')
            ->whereRaw('LOWER(title) LIKE ?', ['%congresso%'])
            ->update(['is_congress' => true]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_congress');
        });
    }
};
