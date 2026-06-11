<?php

namespace App\Filament\Resources\CongressSubscriptions\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Delegate;
use App\Models\Event;
use App\Models\Registration;

class CongressStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $congressEventIds = Event::where('title', 'LIKE', '%congresso%')->pluck('id');

        $delegadosUmp = Delegate::where('type', 'delegado')->count();
        $visitantesUmp = Delegate::where('type', 'visitante')->count();
        
        $visitantesAvulsos = Registration::whereIn('event_id', $congressEventIds)->count();

        $totalVisitantes = $visitantesUmp + $visitantesAvulsos;
        $totalGeral = $delegadosUmp + $totalVisitantes;

        return [
            Stat::make('Total de Delegados Oficiais', $delegadosUmp)
                ->description('Delegados inscritos')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Total de Visitantes', $totalVisitantes)
                ->description('Visitantes inscritos pelo site')
                ->descriptionIcon('heroicon-m-user')
                ->color('gray'),

            Stat::make('Total Geral de Inscritos', $totalGeral)
                ->description('Delegados + Visitantes')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
