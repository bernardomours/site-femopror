@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $graficos = $this->graficos();

    /*
     * Estilo em atributo `style`, não em classe do Tailwind.
     *
     * O layout do Filament não carrega o CSS do app: ele serve o próprio
     * bundle. Classe como `grid-cols-2`, `divide-y` ou `mt-4` simplesmente não
     * existe dentro do painel — fica sem efeito nenhum, e foi o que desmontou
     * esta tela na primeira versão. Aqui só entram classes `fi-*` (do próprio
     * Filament) e estilo inline.
     */
    $grade = 'display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(22rem, 1fr));';
    $linha = 'display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; padding: 0.375rem 0; border-top: 1px solid rgba(0,0,0,0.06);';
    $rotulo = 'color: rgb(107,114,128); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
    $numero = 'flex-shrink: 0; font-weight: 600; font-variant-numeric: tabular-nums;';

    $opcoes = [
        'plugins' => [
            // Uma série só por gráfico — a legenda repetiria o título.
            'legend' => ['display' => false],
        ],
        'maintainAspectRatio' => false,
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                // Pessoas são inteiros: sem isto o eixo mostra 0,5 inscrito.
                'ticks' => ['precision' => 0],
            ],
        ],
    ];
@endphp

{{--
    A classe `fi-wi-chart` não é decorativa: o CSS do Filament que dá cor às
    barras é escopado nela (`.fi-wi-chart .fi-color .fi-wi-chart-bg-color`).
    Sem a classe, nenhuma regra casa, os spans de referência herdam a cor de
    texto da página e o Chart.js desenha tudo preto.
--}}
<x-filament-widgets::widget class="fi-wi-chart">
    @if (empty($graficos))
        <x-filament::section>
            <p style="font-size: 0.875rem; color: rgb(107,114,128);">
                Nenhuma inscrição registrada neste evento ainda. Assim que a primeira
                chegar, os gráficos aparecem aqui — um para cada pergunta do formulário.
            </p>
        </x-filament::section>
    @else
        <div style="{{ $grade }}">
            @foreach ($graficos as $grafico)
                <x-filament::section
                    :heading="$grafico['titulo']"
                    :description="$grafico['subtitulo']"
                >
                    {{--
                        Mesma montagem do ChartWidget do Filament: a Alpine
                        component `chart` já traz o Chart.js e lê as cores do
                        tema pelos elementos de referência abaixo.

                        `@js()` é obrigatório aqui — ele escapa as aspas, que de
                        outro modo encerrariam o atributo x-data e fariam o
                        código vazar como texto na tela.
                    --}}
                    <div
                        x-load
                        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                        {{--
                            A chave inclui um resumo dos dados de propósito.
                            Iguais, o Livewire preserva o elemento e o gráfico
                            não pisca; diferentes, ele troca o elemento e o
                            Alpine monta um Chart.js novo com os números novos.
                            Com `wire:ignore` o gráfico ficaria congelado no
                            primeiro evento aberto; sem chave nenhuma, o morph
                            reaproveitaria o canvas e deixaria o desenho velho.
                        --}}
                        wire:key="grafico-{{ $grafico['id'] }}-{{ substr(md5(json_encode($grafico)), 0, 8) }}"
                        data-chart-type="bar"
                        x-data="chart({
                            cachedData: @js([
                                'labels' => $grafico['rotulos'],
                                'datasets' => [[
                                    'label' => $grafico['titulo'],
                                    'data' => $grafico['valores'],
                                ]],
                            ]),
                            options: @js($opcoes),
                            type: @js('bar'),
                        })"
                        {{-- Altura fixa: sem ela o Chart.js usa a proporção do
                             contêiner e um gráfico com muitas igrejas estica a
                             página inteira. --}}
                        style="height: 14rem;"
                        {{
                            (new ComponentAttributeBag)
                                ->color(ChartWidgetComponent::class, 'primary')
                                ->class(['fi-wi-chart-canvas-ctn', 'fi-wi-chart-canvas-ctn-no-aspect-ratio'])
                        }}
                    >
                        <canvas x-ref="canvas" style="width: 100%; height: 100%;"></canvas>

                        <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                        <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                        <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                        <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                    </div>

                    {{--
                        A lista abaixo do gráfico não é enfeite: é dela que sai o
                        número exato. O gráfico responde "qual é a maior?", mas
                        quem vai comprar camisa ou fechar almoço precisa do
                        valor, e lê melhor numa linha do que estimando altura de
                        coluna.
                    --}}
                    <dl style="margin-top: 1rem; font-size: 0.875rem;">
                        @foreach ($grafico['rotulos'] as $indice => $texto)
                            <div style="{{ $linha }}">
                                <dt style="{{ $rotulo }}">{{ $texto }}</dt>
                                <dd style="{{ $numero }}">{{ $grafico['valores'][$indice] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
