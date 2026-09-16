<?php

namespace App\Filament\Resources\CongressSubscriptions\Widgets;

use App\Models\Delegate;
use App\Models\Event;
use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Os números eram somados sobre TODOS os congressos de todos os anos, sem
 * recorte de evento: o painel só crescia e nunca respondia "quantos vêm neste
 * congresso?". Agora o recorte é o congresso aberto mais recente, identificado
 * pela coluna `is_congress` — antes era `title LIKE '%congresso%'`, que
 * quebrava quando o evento mudava de nome.
 */
class CongressStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $congresso = Event::query()
            ->where('is_congress', true)
            ->orderByDesc('event_date')
            ->first();

        if (! $congresso) {
            return [
                Stat::make('Nenhum congresso cadastrado', '—')
                    ->description('Marque um evento como congresso para ver os números aqui')
                    ->color('gray'),
            ];
        }

        $delegacao = Delegate::query()
            ->whereHas('congressSubscription', fn ($q) => $q->where('event_id', $congresso->id))
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $delegados = (int) ($delegacao['delegado'] ?? 0);
        $visitantesUmp = (int) ($delegacao['visitante'] ?? 0);
        $visitantesAvulsos = Registration::where('event_id', $congresso->id)->count();

        $totalVisitantes = $visitantesUmp + $visitantesAvulsos;

        return [
            Stat::make('Delegados Oficiais', $delegados)
                ->description('Credenciados pelas UMPs locais')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Visitantes', $totalVisitantes)
                ->description($visitantesAvulsos.' pelo site · '.$visitantesUmp.' pelas UMPs')
                ->descriptionIcon('heroicon-m-user')
                ->color('gray'),

            Stat::make('Total de Inscritos', $delegados + $totalVisitantes)
                ->description($congresso->title)
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
