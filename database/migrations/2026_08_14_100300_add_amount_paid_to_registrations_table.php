<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O valor da inscrição era recalculado na tela toda vez, a partir do texto
     * das respostas. Quem confere o comprovante precisa saber quanto o sistema
     * cobrou daquela pessoa naquele dia — inclusive se o preço do evento ou o
     * adicional da camisa mudar depois. Então o valor congela na inscrição.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->nullable()->after('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });
    }
};
