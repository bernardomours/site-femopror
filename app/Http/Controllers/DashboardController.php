<?php

namespace App\Http\Controllers;

use App\Models\Delegate;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Área do participante: o que ele se inscreveu sozinho e onde a UMP dele o
     * credenciou como delegado.
     *
     * Isto era uma closure na rota. Além do lugar errado, tinha dois problemas:
     * carregava `with('congressSubscription.church')` sobre uma relação que o
     * model não definia (erro 500 para todo delegado de verdade), e procurava a
     * delegação só por e-mail — quem trocasse o e-mail no perfil perdia a
     * própria inscrição de vista.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $inscricoesAvulsas = Registration::where('user_id', $user->id)
            ->with(['event', 'church'])
            ->latest()
            ->get();

        $inscricoesDelegado = Delegate::query()
            ->where('user_id', $user->id)
            // O e-mail segue valendo para a delegação cadastrada antes de a
            // conta existir e que o vínculo automático ainda não alcançou.
            ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
            ->with(['congressSubscription.church', 'congressSubscription.event'])
            ->latest()
            ->get();

        return view('dashboard', [
            'inscricoesAvulsas' => $inscricoesAvulsas,
            'inscricoesDelegado' => $inscricoesDelegado,
        ]);
    }
}
