<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Support\PanoramaDoEvento;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Os gráficos de colunas do evento selecionado.
 *
 * É **um** widget que desenha N gráficos, e não N widgets — de propósito. A
 * quantidade de gráficos depende de quantas perguntas o evento tem, e isso muda
 * quando o filtro muda. Registrar widgets dinamicamente a cada troca de filtro
 * faria o Livewire montar e desmontar componentes no meio de um re-render; com
 * um componente só, a troca de evento é uma re-renderização comum.
 *
 * O desenho em si reaproveita a Alpine component `chart` do próprio Filament
 * (a mesma do `ChartWidget`), então cor, fonte e tema acompanham o painel sem
 * nenhum CSS novo e sem outra cópia do Chart.js na página.
 */
class PanoramaDoEventoGraficos extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.panorama-do-evento-graficos';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return list<array{id: string, titulo: string, subtitulo: string, rotulos: list<string>, valores: list<int>}>
     */
    public function graficos(): array
    {
        $evento = Event::find($this->pageFilters['eventId'] ?? null);

        return $evento ? PanoramaDoEvento::para($evento)->graficos() : [];
    }
}
