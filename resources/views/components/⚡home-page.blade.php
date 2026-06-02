<?php

use Livewire\Component;
use App\Models\Event;
use Carbon\Carbon;

new class extends Component
{
    public function with(): array
    {
        return [
            // Busca apenas eventos publicados, ordenando pela data mais próxima
            'events' => Event::where('status', 'published')
                             ->orderBy('event_date', 'asc')
                             ->get(),
        ];
    }
};
?>

<div class="min-h-screen bg-gray-50">
    <!-- Navbar Simples -->
    <nav class="bg-white border-b border-gray-200 py-4 px-8 flex items-center justify-between">
        <div class="flex-shrink-0">
            <img src="{{ asset('images/topo.png') }}" alt="Logo" class="h-14 brightness-0">
        </div>

        <div class="flex items-center gap-8">
            <a href="#" class="text-xs font-bold text-gray-800 uppercase hover:text-red-700">Nossa História</a>
            <a href="#" class="text-xs font-bold text-gray-800 uppercase hover:text-red-700">Secretarias</a>
            <a href="#" class="text-xs font-bold text-gray-800 uppercase hover:text-red-700">Diretoria</a>
            <a href="#eventos" class="text-xs font-bold text-gray-800 uppercase hover:text-red-700">Eventos</a>
            <a href="/admin/login" class="text-xs font-bold text-gray-800 uppercase hover:text-red-700">Login</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="w-full bg-[#0a261a]">
        <!-- Imagem do Tema -->
        <div class="w-full">
            <img src="{{ asset('images/tema-ano.jpg') }}" 
                alt="Crescer e Frutificar" 
                class="w-full h-auto max-h-[500px] object-contain mx-auto">
        </div>

        <!-- Botões de Ação -->
        <div class="w-full bg-[#0a261a] py-6 flex flex-wrap justify-center gap-4 border-t border-green-900">
            <a href="#eventos" class="bg-white text-green-900 px-8 py-3 rounded-full font-bold shadow-lg hover:bg-green-50 transition">
                Ver Próximos Eventos
            </a>
            <a href="#" class="border-2 border-white text-white px-8 py-3 rounded-full font-bold hover:bg-white hover:text-green-900 transition">
                Conheça a Diretoria
            </a>
        </div>
    </div>

    <!-- Seção Dinâmica de Eventos -->
    <div id="eventos" class="py-20 px-6 md:px-12 max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800">O que vem por aí?</h2>
            <p class="text-gray-500 mt-2">Confira nossos próximos encontros e garanta sua vaga!</p>
        </div>

        @if($events->isEmpty())
            <!-- Estado Vazio (Caso não tenha evento publicado) -->
            <div class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum evento aberto</h3>
                <p class="mt-1 text-sm text-gray-500">Estamos preparando novidades. Fique ligado nas nossas redes sociais!</p>
            </div>
        @else
            <!-- Grid de Eventos -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($events as $event)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col hover:shadow-md transition">
                        <div class="p-6 flex-grow">
                            <div class="flex justify-between items-start mb-4">
                                <span class="bg-red-100 text-red-800 text-xs font-bold px-3 py-1 rounded-full tracking-wide">
                                    {{ Carbon::parse($event->event_date)->format('d/m/Y H:i') }}
                                </span>
                                @if($event->price > 0)
                                    <span class="text-gray-900 font-bold">R$ {{ number_format($event->price, 2, ',', '.') }}</span>
                                @else
                                    <span class="text-green-600 font-bold uppercase text-sm">Gratuito</span>
                                @endif
                            </div>
                            
                            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $event->title }}</h3>
                            <p class="text-gray-600 line-clamp-3 mb-4">
                                {{ $event->description ?? 'Detalhes do evento em breve.' }}
                            </p>
                            
                            @if($event->location)
                                <div class="flex items-center text-sm text-gray-500 mb-6">
                                    <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $event->location }}
                                </div>
                            @endif
                        </div>
                        
                        <div class="p-6 pt-0 mt-auto">
                            <a href="#" class="block w-full text-center bg-gray-50 hover:bg-gray-100 text-red-700 font-semibold py-3 rounded-xl border border-gray-200 transition">
                                Ver Detalhes
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>