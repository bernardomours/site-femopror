<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PanoramaDoEventoGraficos;
use App\Filament\Widgets\PanoramaDoEventoTotais;
use App\Models\Event;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * Painel de acompanhamento de evento.
 *
 * O dashboard estava vazio. Agora ele responde uma pergunta só, que é a que a
 * diretoria faz o tempo todo antes de um evento: **como está a inscrição?**
 *
 * O recorte é um evento por vez, escolhido no seletor do topo. Sem o seletor, a
 * alternativa seria somar todos os eventos de todos os anos num número só — que
 * é justamente o defeito que o `CongressStatsWidget` já teve: o painel só
 * crescia e nunca respondia quantos vêm *neste* evento.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Acompanhamento';

    protected static ?string $navigationLabel = 'Acompanhamento';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('eventId')
                ->label('Evento')
                ->options(fn () => Event::query()
                    ->orderByDesc('event_date')
                    ->pluck('title', 'id'))
                ->default(fn () => static::eventoPadrao())
                // Sem isso o usuário consegue esvaziar o seletor e olhar para
                // uma tela em branco sem entender por quê.
                ->selectablePlaceholder(false)
                ->searchable()
                ->native(false),
        ]);
    }

    /**
     * Abre no evento que está sendo trabalhado agora: o mais próximo que ainda
     * não passou. Se todos já passaram, o último — é o que se confere depois.
     */
    protected static function eventoPadrao(): ?int
    {
        return Event::query()->where('event_date', '>=', now())->orderBy('event_date')->value('id')
            ?? Event::query()->orderByDesc('event_date')->value('id');
    }

    public function getWidgets(): array
    {
        return [
            PanoramaDoEventoTotais::class,
            PanoramaDoEventoGraficos::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 1;
    }
}
