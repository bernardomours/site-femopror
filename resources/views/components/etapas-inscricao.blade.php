@props([
    // Páginas do formulário, já com "Seus dados" na frente.
    'paginas' => [],
    // Índice da página que está aberta, ou `null` quando a pessoa já passou
    // do preenchimento e está no pagamento.
    'pagina' => 0,
    // Mostra o passo "Pagamento" no fim. Evento gratuito não tem.
    'pagamento' => true,
    'noPagamento' => false,
])

@php
    $passos = collect($paginas)->map(fn ($p) => $p['title'])->values();

    if ($pagamento) {
        $passos->push('Pagamento');
    }

    $atual = $noPagamento ? $passos->count() - 1 : $pagina;
@endphp

{{--
    O mapa do processo, do começo ao fim.

    Era "① Seus dados — ② Pagamento" fixo, numa barra horizontal. Agora as
    etapas são as páginas que a diretoria cadastrou, mais o pagamento, e o
    passo concluído ganha o ✓ — o ponto é a pessoa saber de cara quantas telas
    faltam, em vez de descobrir uma de cada vez.

    Coluna à esquerda no desktop, linha rolável no celular: empilhar seis
    passos numa tela de 375px comeria a dobra inteira antes do primeiro campo.
--}}
<nav aria-label="Etapas da inscrição" class="mb-6 lg:mb-0">
    {{--
        No celular, a lista inteira não cabe: quatro passos dão ~580px numa
        tela de 375px, e rolagem lateral é um padrão que quase ninguém percebe
        — a pessoa veria "1 Seus dados 2 Inform..." e concluiria que são dois.
        Então ali vira "Passo 2 de 4" com barra de progresso, que ocupa uma
        linha e diz a mesma coisa sem esconder nada.
    --}}
    <div class="lg:hidden">
        <div class="flex items-baseline justify-between gap-3">
            <span class="text-xs font-bold uppercase tracking-wider text-green-900">
                Passo {{ $atual + 1 }} de {{ $passos->count() }}
            </span>
            <span class="truncate text-sm font-semibold text-gray-900">{{ $passos[$atual] }}</span>
        </div>

        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full bg-green-900 transition-all duration-300"
                 style="width: {{ round((($atual + 1) / max($passos->count(), 1)) * 100) }}%"></div>
        </div>
    </div>

    <ol class="hidden lg:flex lg:flex-col">
        @foreach($passos as $indice => $titulo)
            @php
                $concluido = $indice < $atual;
                $ehAtual = $indice === $atual;
            @endphp

            <li class="flex items-start gap-3 pb-1">
                <div class="flex flex-col items-center self-stretch">
                    <span @class([
                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        'bg-green-900 text-white' => $concluido,
                        'bg-green-100 text-green-900 ring-2 ring-green-900' => $ehAtual,
                        'bg-gray-100 text-gray-400' => ! $concluido && ! $ehAtual,
                    ])>
                        @if($concluido)
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            <span class="sr-only">concluída</span>
                        @else
                            {{ $indice + 1 }}
                        @endif
                    </span>

                    {{-- Fio ligando um passo ao outro. --}}
                    @if(! $loop->last)
                        <span class="w-px flex-1 bg-gray-200" aria-hidden="true"></span>
                    @endif
                </div>

                <span @class([
                    'pt-1 pb-6 text-sm leading-tight',
                    'font-bold text-green-900' => $ehAtual,
                    'font-medium text-gray-900' => $concluido,
                    'text-gray-400' => ! $concluido && ! $ehAtual,
                ]) @if($ehAtual) aria-current="step" @endif>
                    {{ $titulo }}
                </span>
            </li>
        @endforeach
    </ol>
</nav>
