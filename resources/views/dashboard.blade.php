<x-app-layout>
    <x-slot name="title">Minhas inscrições</x-slot>

    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-gray-900">Minhas inscrições</h2>
            <p class="mt-1 text-sm text-gray-500">Olá, {{ explode(' ', auth()->user()->name)[0] }}. Aqui ficam os eventos em que você está inscrito e o andamento de cada um.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4 px-4 py-10 sm:px-6 lg:px-8">

        @if(blank(auth()->user()->church_id) || blank(auth()->user()->phone))
            {{-- Perfil incompleto significa digitar igreja e telefone de novo a
                 cada inscrição. Um aviso discreto, sem virar banner. --}}
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 16v-4m0-3.5h.01" />
                    </svg>
                    <p class="text-sm leading-relaxed text-gray-600">
                        Complete seu perfil com a <strong class="font-medium text-gray-900">igreja</strong> e o
                        <strong class="font-medium text-gray-900">WhatsApp</strong> — assim as próximas inscrições já vêm preenchidas.
                    </p>
                </div>

                <a href="{{ route('profile.edit') }}" class="flex-shrink-0 rounded-lg border border-gray-200 px-3.5 py-2 text-center text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                    Completar perfil
                </a>
            </div>
        @endif

        @foreach($inscricoesAvulsas as $inscricao)
            <div x-data="{ showDetails: false }" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="border-l-2 border-green-800 p-6">

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Inscrição individual</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $inscricao->event->title ?? 'Evento indisponível' }}</h3>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
                                <span>Enviada em {{ $inscricao->created_at->format('d/m/Y') }}</span>

                                @if($inscricao->amount_paid !== null)
                                    <span class="tabular-nums">R$ {{ number_format((float) $inscricao->amount_paid, 2, ',', '.') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col items-start gap-3 sm:flex-shrink-0 sm:items-end">
                            @php($cor = $inscricao->statusColor())
                            <span @class([
                                'inline-block rounded-lg px-3 py-1 text-xs font-semibold',
                                'bg-green-50 text-green-800' => $cor === 'success',
                                'bg-blue-50 text-blue-800' => $cor === 'info',
                                'bg-amber-50 text-amber-800' => $cor === 'warning',
                                'bg-red-50 text-red-700' => $cor === 'danger',
                            ])>
                                {{ $inscricao->statusLabel() }}
                            </span>

                            {{-- A inscrição é salva antes do pagamento. Quem fechou a aba no meio
                                 (o app do banco costuma fazer isso no celular) retoma por aqui. --}}
                            @if($inscricao->isAwaitingReceipt())
                                <a href="{{ route('events.show', $inscricao->event_id) }}"
                                   class="inline-flex items-center rounded-lg bg-green-900 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-800">
                                    Pagar e enviar comprovante
                                </a>
                            @elseif($inscricao->canReplaceReceipt())
                                {{-- Mandou o arquivo errado: dá para trocar enquanto a tesouraria
                                     não confirmou. --}}
                                <a href="{{ route('events.show', $inscricao->event_id) }}"
                                   class="inline-flex items-center rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                                    Visualizar comprovante
                                </a>
                            @endif

                            <button type="button" @click="showDetails = ! showDetails" :aria-expanded="showDetails"
                                    class="flex items-center gap-1 text-sm font-medium text-gray-500 transition-colors hover:text-green-900">
                                <span x-text="showDetails ? 'Ocultar detalhes' : 'Ver detalhes'">Ver detalhes</span>
                                <svg class="h-4 w-4 transition-transform duration-200" :class="{'rotate-180': showDetails}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div x-show="showDetails" x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="mt-6 border-t border-gray-100 pt-6">

                        <h4 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Dados enviados</h4>

                        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            @foreach([
                                ['Nome completo', $inscricao->name],
                                ['E-mail', $inscricao->email],
                                ['WhatsApp', $inscricao->phone],
                                ['Igreja local', $inscricao->church->name ?? 'Não informada'],
                            ] as [$rotulo, $valor])
                                <div>
                                    <dt class="mb-0.5 text-xs font-medium text-gray-500">{{ $rotulo }}</dt>
                                    <dd class="text-sm text-gray-900">{{ $valor }}</dd>
                                </div>
                            @endforeach

                            {{-- O comprovante está em disco privado: sem este link a pessoa
                                 não conseguia nem conferir qual arquivo tinha mandado. --}}
                            @if(filled($inscricao->receipt_path))
                                @php($urlComprovante = $inscricao->receiptUrl())
                                <div>
                                    <dt class="mb-0.5 text-xs font-medium text-gray-500">Comprovante</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($urlComprovante)
                                            <a href="{{ $urlComprovante }}" target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center gap-1 font-medium text-green-900 underline underline-offset-2 hover:text-green-700">
                                                Ver {{ $inscricao->receiptIsPdf() ? 'PDF' : 'imagem' }} enviado
                                            </a>
                                        @else
                                            <span class="text-gray-400">Indisponível no momento</span>
                                        @endif
                                    </dd>
                                </div>
                            @endif

                            @if(is_array($inscricao->custom_answers))
                                @foreach($inscricao->custom_answers as $pergunta => $resposta)
                                    <div>
                                        <dt class="mb-0.5 text-xs font-medium text-gray-500">{{ $pergunta }}</dt>
                                        <dd class="text-sm text-gray-900">
                                            @if(blank($resposta))
                                                <span class="text-gray-400">Não respondido</span>
                                            @elseif(is_array($resposta))
                                                {{ implode(', ', $resposta) }}
                                            @else
                                                {{ $resposta }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            @endif
                        </dl>
                    </div>

                </div>
            </div>
        @endforeach

        @foreach($inscricoesDelegado as $delegado)
            @php($inscricaoUmp = $delegado->congressSubscription)

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="border-l-2 border-blue-700 p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                {{ $delegado->type === 'visitante' ? 'Visitante pela UMP' : 'Delegado oficial' }}
                            </p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $inscricaoUmp?->event?->title ?? 'Congresso a definir' }}</h3>

                            <p class="mt-2 text-sm text-gray-500">
                                Inscrito pela {{ $inscricaoUmp?->church?->name ?? 'sua igreja' }}.
                            </p>
                        </div>

                        @if($inscricaoUmp)
                            <span @class([
                                'inline-block flex-shrink-0 rounded-lg px-3 py-1 text-xs font-semibold',
                                'bg-green-50 text-green-800' => $inscricaoUmp->status === 'aprovado',
                                'bg-red-50 text-red-700' => $inscricaoUmp->status === 'recusado',
                                'bg-amber-50 text-amber-800' => ! in_array($inscricaoUmp->status, ['aprovado', 'recusado']),
                            ])>
                                {{ match($inscricaoUmp->status) {
                                    'aprovado' => 'Confirmada',
                                    'recusado' => 'Com pendências',
                                    default => 'Em análise',
                                } }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        @if($inscricoesAvulsas->isEmpty() && $inscricoesDelegado->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <svg class="mx-auto mb-4 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>

                <h3 class="text-base font-semibold text-gray-900">Nenhuma inscrição ainda</h3>
                <p class="mx-auto mt-1.5 max-w-sm text-sm leading-relaxed text-gray-500">
                    Você ainda não se inscreveu em nenhum evento, e a sua UMP não te incluiu na lista de delegados.
                </p>

                <a href="{{ route('home') }}#eventos" class="mt-6 inline-flex items-center rounded-lg bg-green-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-green-800">
                    Ver próximos eventos
                </a>
            </div>
        @endif

    </div>
</x-app-layout>
