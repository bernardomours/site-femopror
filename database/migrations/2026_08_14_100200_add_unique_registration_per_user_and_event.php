<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Não havia nada impedindo a mesma pessoa se inscrever duas vezes no mesmo
     * evento — a checagem só existia na view. Duplo clique, aba reaberta ou
     * request forjada geravam linhas repetidas.
     *
     * ATENÇÃO: esta migration APAGA duplicatas existentes. Faça backup antes.
     * A linha preservada é sempre a de pagamento confirmado; havendo empate,
     * a mais antiga (a que a pessoa realmente quis fazer). Nenhuma inscrição
     * com pagamento já confirmado pela tesouraria é removida.
     */
    public function up(): void
    {
        $this->removerDuplicatas();

        Schema::table('registrations', function (Blueprint $table) {
            $table->unique(['event_id', 'user_id'], 'registrations_event_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_event_user_unique');
        });
    }

    private function removerDuplicatas(): void
    {
        $grupos = DB::table('registrations')
            ->select('event_id', 'user_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('user_id')
            ->groupBy('event_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $removidas = 0;

        foreach ($grupos as $grupo) {
            $linhas = DB::table('registrations')
                ->where('event_id', $grupo->event_id)
                ->where('user_id', $grupo->user_id)
                ->orderBy('id')
                ->get(['id', 'payment_status']);

            // Pagamento confirmado ganha; senão, a mais antiga.
            $manter = $linhas->firstWhere('payment_status', 'paid')?->id ?? $linhas->first()->id;

            $removidas += DB::table('registrations')
                ->where('event_id', $grupo->event_id)
                ->where('user_id', $grupo->user_id)
                ->where('id', '!=', $manter)
                ->delete();
        }

        if ($removidas > 0) {
            echo "  [inscrições] {$removidas} duplicata(s) removida(s) antes de criar o índice único.".PHP_EOL;
        }
    }
};
