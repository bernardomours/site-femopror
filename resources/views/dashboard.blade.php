<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Painel do Participante
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-lg">
                    Olá, <strong>{{ auth()->user()->name }}</strong>! Bem-vindo(a) à sua área exclusiva.
                </div>
            </div>

            @foreach($inscricoesAvulsas as $inscricao)
            <div x-data="{ showDetails: false }" class="bg-white border-l-4 border-green-600 overflow-hidden shadow-sm sm:rounded-lg mb-4">
                <div class="p-6">
                    
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-green-800 mb-1">Inscrição Individual</h3>
                            <p class="text-gray-600"><strong>Evento:</strong> {{ $inscricao->event->title ?? 'Evento Indisponível' }}</p>
                            <p class="text-gray-500 text-sm mt-1"> Inscrição realizada em {{ $inscricao->created_at->format('d/m/Y') }}</p>
                        </div>
                        
                        <div class="flex flex-col items-start sm:items-end gap-3 flex-shrink-0">
                            <span class="px-4 py-1.5 {{ $inscricao->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }} rounded-full text-sm font-bold uppercase tracking-wider shadow-sm inline-block">
                                {{ $inscricao->payment_status === 'paid' ? 'Pago / Confirmado' : 'Inscrição em Análise' }}
                            </span>

                            <button type="button" @click="showDetails = !showDetails" class="text-sm text-green-700 hover:text-green-900 font-bold flex items-center gap-1 transition-colors">
                                <span x-text="showDetails ? 'Ocultar detalhes' : 'Ver detalhes da inscrição'"></span>
                                <svg class="w-4 h-4 transition-transform duration-300" :class="{'rotate-180': showDetails}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div x-show="showDetails" 
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-[-10px]"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        style="display: none;" 
                        class="mt-6 pt-6 border-t border-gray-100">
                        
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Dados fornecidos na inscrição</h4>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs font-semibold text-gray-500 mb-0.5">Nome Completo</span>
                                <span class="text-gray-900 font-medium">{{ $inscricao->name }}</span>
                            </div>
                            
                            <div>
                                <span class="block text-xs font-semibold text-gray-500 mb-0.5">E-mail</span>
                                <span class="text-gray-900 font-medium">{{ $inscricao->email }}</span>
                            </div>
                            
                            <div>
                                <span class="block text-xs font-semibold text-gray-500 mb-0.5">WhatsApp / Telefone</span>
                                <span class="text-gray-900 font-medium">{{ $inscricao->phone }}</span>
                            </div>
                            
                            <div>
                                <span class="block text-xs font-semibold text-gray-500 mb-0.5">Igreja Local</span>
                                <span class="text-gray-900 font-medium">{{ $inscricao->church->name ?? 'Não informada' }}</span>
                            </div>

                            @if(is_array($inscricao->custom_answers))
                                @foreach($inscricao->custom_answers as $pergunta => $resposta)
                                    <div>
                                        <span class="block text-xs font-semibold text-gray-500 mb-0.5">{{ $pergunta }}</span>
                                        <span class="text-gray-900 font-medium">
                                            @if(empty($resposta))
                                                Não respondido
                                            @elseif(is_array($resposta))
                                                {{ implode(', ', $resposta) }}
                                            @else
                                                {{ $resposta }}
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        @endforeach

            @foreach($inscricoesDelegado as $delegado)
                <div class="bg-white border-l-4 border-blue-600 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-2">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <h3 class="text-lg font-bold text-blue-800">Delegado Oficial</h3>
                        </div>
                        <p class="text-gray-600">Você foi inscrito(a) como credenciado oficial pela sua igreja.</p>
                        <p class="text-gray-600 mt-2"><strong>UMP Local:</strong> {{ $delegado->congressSubscription->church->name ?? 'Sua Igreja' }}</p>
                    </div>
                </div>
            @endforeach

            @if($inscricoesAvulsas->isEmpty() && $inscricoesDelegado->isEmpty())
                <div class="bg-gray-50 border border-gray-200 border-dashed rounded-lg p-10 text-center">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    <h3 class="text-lg font-bold text-gray-900">Nenhuma inscrição encontrada</h3>
                    <p class="text-gray-500 mt-2">Você ainda não está inscrito em nenhum evento ou o presidente da sua UMP ainda não enviou a lista de delegados.</p>
                    <a href="/#eventos" class="inline-block mt-4 px-6 py-2 bg-green-800 text-white font-bold rounded-lg hover:bg-green-700 transition-colors">
                        Ver Próximos Eventos
                    </a>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>