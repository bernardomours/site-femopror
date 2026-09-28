<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Support\PanoramaDoEvento;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Os quatro números do topo, sempre do evento selecionado no filtro.
 */
class PanoramaDoEventoTotais extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $evento = Event::find($this->pageFilters['eventId'] ?? null);

        if (! $evento) {
            return [
                Stat::make('Nenhum evento selecionado', '—')
                    ->description('Escolha um evento no seletor acima')
                    ->color('gray'),
            ];
        }

        $t = PanoramaDoEvento::para($evento)->totais();

        return [
            Stat::make('Inscritos', $t['inscritos'])
                ->description($evento->title)
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Pagamentos confirmados', $t['confirmados'])
                ->description($t['aguardando'].' ainda aguardando conferência')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($t['aguardando'] > 0 ? 'warning' : 'success'),

            Stat::make('Arrecadado', 'R$ '.number_format($t['arrecadado'], 2, ',', '.'))
                ->description('Somente inscrições já confirmadas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('A receber', 'R$ '.number_format($t['a_receber'], 2, ',', '.'))
                ->description('Inscrições pendentes de confirmação')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
