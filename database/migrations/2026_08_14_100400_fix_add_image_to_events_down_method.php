<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `2026_06_03_124123_add_image_to_events_table` tinha `down()` vazio: dar
     * rollback deixava a coluna para trás e o re-run quebrava com "duplicate
     * column". Como aquela migration já rodou em produção, editá-la não teria
     * efeito nenhum — a correção precisa ser uma migration nova.
     *
     * `up()` é intencionalmente inerte: a coluna já existe e é o estado certo.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'image')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('image')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'image')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
