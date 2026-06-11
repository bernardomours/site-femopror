<?php

use Livewire\Component;
use App\Models\Event;
use Carbon\Carbon;
use App\Models\Church;
use App\Models\Download;
use Livewire\Attributes\Layout;

new #[Layout('layouts.public')] class extends Component
{
    public function with(): array
    {
        return [
            'events' => Event::where('status', 'published')
                             ->orderBy('event_date', 'asc')
                             ->get(),

            'downloads' => Download::where('is_active', true)->get(),

            'igrejas' => Church::with(['boards' => function ($query) {
                $query->where('is_active', true);
            }])->orderBy('name', 'asc')->get(),
        ];
    }
};

?>

<!-- navbar -->
<div class="min-h-screen bg-gray-50">
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-100 py-4 px-8 flex items-center justify-between shadow-sm">
    
        <div class="flex-shrink-0">
            <a href="/">
                <img src="{{ asset('images/topo.png') }}" alt="Logo FEMOPROR" class="h-12 brightness-0 transition transform hover:scale-105">
            </a>
        </div>

        <div class="flex items-center gap-8">
            <a href="#historia" class="text-xs font-bold text-gray-800 uppercase tracking-widest hover:text-green-700 transition">Nossa História</a>
            <a href="#diretoria" class="text-xs font-bold text-gray-800 uppercase tracking-widest hover:text-green-700 transition">Diretoria</a>
            <a href="#eventos" class="text-xs font-bold text-gray-800 uppercase tracking-widest hover:text-green-700 transition">Eventos</a>
            <a href="#downloads" class="text-xs font-bold text-gray-800 uppercase tracking-widest hover:text-green-700 transition">Downloads</a>

            @guest
                <a href="{{ route('login') }}" class="text-xs font-bold text-white bg-green-900 px-6 py-2.5 rounded-full uppercase tracking-widest hover:bg-green-800 transition shadow-md hover:-translate-y-0.5 transform">
                    Login
                </a>
            @endguest

            @auth
                <!-- Dropdown do Usuário (Aparece só para quem está logado) -->
                <div x-data="{ open: false }" class="relative">
                    
                    <!-- Botão com Ícone e Primeiro Nome -->
                    <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 text-xs font-bold text-green-900 bg-green-50 px-4 py-2.5 rounded-full uppercase tracking-widest hover:bg-green-100 transition shadow-sm border border-green-200">
                        <!-- Ícone de Usuário -->
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        
                        <!-- Pega apenas o primeiro nome do usuário -->
                        {{ explode(' ', Auth::user()->name)[0] }}
                        
                        <!-- Ícone de Setinha para baixo -->
                        <svg class="w-3 h-3 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <!-- Menu Dropdown Invisível que abre ao clicar -->
                    <div x-show="open" 
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        style="display: none;" 
                        class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl py-2 border border-gray-100 z-50">
                        
                        <!-- Link para o Dashboard -->
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-green-50 hover:text-green-900 font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                            Minhas Inscrições
                        </a>

                        <div class="h-px bg-gray-100 my-1"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-medium transition-colors text-left">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </nav>

    <!-- section do tema anual e botoes -->
    <div class="w-full bg-green-900 flex flex-col items-center justify-center py-6 md:py-8 px-4 border-b-8 border-green-950">
        
        <div class="w-full max-w-2xl mb-5 flex justify-center">
            <img src="{{ asset('images/tema-anual.png') }}" 
                 alt="Tema do Ano: Crescer e Frutificar" 
                 class="w-full max-w-xs md:max-w-md h-auto object-contain drop-shadow-2xl">
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center w-full max-w-md sm:max-w-2xl">
            <a href="#eventos" class="bg-white text-green-900 px-8 py-3 rounded-full font-bold text-lg shadow-lg hover:bg-gray-100 transition w-full sm:w-auto text-center">
                Ver Próximos Eventos
            </a>
            <a href="#diretoria" class="border-2 border-white text-white px-8 py-3 rounded-full font-bold text-lg shadow-lg hover:bg-white hover:text-green-900 transition w-full sm:w-auto text-center">
                Conheça a Diretoria
            </a>
        </div>
        
    </div> 

    <!-- section sobre nos -->
    <div id="historia" class="py-20 px-6 md:px-12 max-w-7xl mx-auto bg-white mb-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            
            <div class="space-y-6">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-2 uppercase tracking-tight">Sobre Nós</h2>
                    <div class="w-16 h-1.5 bg-green-900 rounded-full mb-6"></div>
                </div>

                <h3 class="text-xl font-bold text-gray-800 leading-snug">
                    A FEMOPROR é a força da juventude no Presbitério Oeste Rio-Grandense.
                </h3>
                
                <p class="text-gray-600 leading-relaxed">
                    Nossa federação atua com o objetivo de integrar as mocidades locais, promovendo comunhão, crescimento espiritual e engajamento no trabalho do Senhor. Reunimos jovens dedicados a servir e transformar a nossa região através do evangelho.
                </p>

                <ul class="space-y-3 mt-4 text-gray-700">
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Fortalecimento das sociedades internas locais (UMP).</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Realização de encontros.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Alegres na esperança, fortes na fé e dedicados no amor.</span>
                    </li>
                </ul>

                <p class="text-gray-600 leading-relaxed mt-4">
                    Com entusiasmo, compromisso e total dependência de Deus, avançamos para o alvo, apoiando o trabalho em Mossoró e região.
                </p>
            </div>

            <div class="relative rounded-2xl overflow-hidden shadow-2xl group">
                <img src="{{ asset('images/sobre-nos.png') }}"
                     alt="Nossa História - FEMOPROR" 
                     class="w-full h-[400px] lg:h-[500px] object-cover transition-transform duration-700 group-hover:scale-105">
                
                <div class="absolute inset-0 border-4 border-white/20 rounded-2xl pointer-events-none"></div>
            </div>

        </div>
    </div>

    <!-- section diretoria -->
     <div id="diretoria" class="py-20 px-6 md:px-12 max-w-7xl mx-auto bg-white" >
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

        <div class="relative rounded-2xl overflow-hidden shadow-2xl group">
                <img src="{{ asset('images/diretoria-2026.png') }}"
                     alt="Nossa História - FEMOPROR" 
                     class="w-full h-auto object-contain rounded-2xl transition-transform duration-700 group-hover:scale-105">
                
                <div class="absolute inset-0 border-4 border-white/20 rounded-2xl pointer-events-none"></div>
            </div>
            
            <div class="space-y-6">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-2 uppercase tracking-tight">DIRETORIA</h2>
                    <div class="w-16 h-1.5 bg-green-900 rounded-full mb-6"></div>
                </div>

                <h3 class="text-xl font-bold text-gray-800 leading-snug">
                    A Diretoria da Federação eleita para o ano de 2026.
                </h3>
                
                <p class="text-gray-600 leading-relaxed">
                    Jovens eleitos para servirem ao Senhor e representarem as UMPs locais.
                </p>

                <ul class="space-y-4 mt-4 text-gray-700">
                    <li class="flex items-center gap-3">
                        <span class="text-sm">
                            <strong class="text-gray-900">Presidente:</strong> 
                            <a href="https://www.instagram.com/ezequiel.meira/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 hover:underline transition font-semibold ml-1">
                                Ezequiel Meira
                            </a>
                        </span>
                    </li>

                    <li class="flex items-center gap-3">
                        <span class="text-sm">
                            <strong class="text-gray-900">Vice-Presidente:</strong> 
                            <a href="https://www.instagram.com/constzza/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 hover:underline transition font-semibold ml-1">
                                Constanzza Nascimento
                            </a>
                        </span>
                    </li>

                    <li class="flex items-center gap-3">
                        <span class="text-sm">
                            <strong class="text-gray-900">Sec. Executivo:</strong> 
                            <a href="https://www.instagram.com/clarohanaa/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 hover:underline transition font-semibold ml-1">
                                Clara Ohana
                            </a>
                        </span>
                    </li>

                    <li class="flex items-center gap-3">
                        <span class="text-sm">
                            <strong class="text-gray-900">1º Secretário:</strong> 
                            <a href="https://www.instagram.com/claravaleo/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 hover:underline transition font-semibold ml-1">
                                Clara Manuella
                            </a>
                        </span>
                    </li>

                    <li class="flex items-center gap-3">
                        <span class="text-sm">
                            <strong class="text-gray-900">2º Secretário:</strong> 
                            <a href="https://www.instagram.com/bernardomours/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 hover:underline transition font-semibold ml-1">
                                 Bernardo Moura
                            </a>
                        </span>
                    </li>

                    <li class="flex items-center gap-3">
                        <span class="text-sm">
                            <strong class="text-gray-900">Tesoureiro:</strong> 
                            <a href="https://www.instagram.com/adsonavelino/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 hover:underline transition font-semibold ml-1">
                                Adson Avelino
                            </a>
                        </span>
                    </li>

                    <li class="flex items-center gap-3">
                        <span class="text-sm">
                            <strong class="text-gray-900">Sec. Presbiterial:</strong> 
                            <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 hover:underline transition font-semibold ml-1">
                                Antônio Alex
                            </a>
                        </span>
                    </li>
                </ul>

                <p class="text-gray-600 leading-relaxed mt-4">
                    Com entusiasmo, compromisso e total dependência de Deus, avançamos para o alvo, apoiando o trabalho em Mossoró e região.
                </p>
            </div>

        </div>
    </div>

    <!-- section Quem compõe a FEMOPROR? -->
    <div class="py-16 px-6 md:px-12 max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 uppercase tracking-tight mb-2">Quem compõe a FEMOPROR?</h2>
            <div class="w-16 h-1.5 bg-green-900 rounded-full mx-auto"></div>
            <p class="text-gray-600 mt-4">Igrejas que caminham juntas na missão e no serviço ao Senhor.</p>
        </div>

        @php
            // A busca agora carrega as diretorias ativas e mantém a sua ordenação inteligente
            $churches = \App\Models\Church::where('is_federation', true)
                ->with(['boards' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->orderByRaw("
                    TRIM(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(LOWER(name), 'igreja ', ''), 
                                    ' das ', ' '), 
                                ' dos ', ' '), 
                            ' do ', ' '), 
                        ' de ', ' ')
                    ) ASC
                ")
                ->get();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($churches as $church)
                @php
                    // Pega a diretoria ativa carregada na busca acima
                    $diretoria = $church->boards->first();
                @endphp

                <div x-data="{ modalAberto: false }" class="relative">
                    
                    <button @click="modalAberto = true" type="button" class="w-full text-left bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-green-200 transition-all duration-300 flex items-center gap-4 group">
                        <div class="w-12 h-12 bg-green-50 rounded-full flex items-center justify-center flex-shrink-0 group-hover:scale-110 group-hover:bg-green-100 transition-all">
                            <svg class="w-6 h-6 text-green-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V10.5M12 21V4M5 21V10.5M3 21h18M3 10.5l9-7.5 9 7.5"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 group-hover:text-green-900 transition-colors">{{ $church->name }}</h3>
                        </div>
                    </button>

                    <div x-show="modalAberto" 
                        style="display: none;"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 text-left"
                        @keydown.escape.window="modalAberto = false"
                        x-cloak>
                        
                        <div x-show="modalAberto" 
                            x-transition.opacity
                            @click="modalAberto = false"
                            class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>

                        <div x-show="modalAberto"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                            class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                            
                            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-gray-50 flex-shrink-0">
                                <h3 class="text-xl font-bold text-green-950 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    {{ $church->name }}
                                </h3>
                                <button type="button" @click="modalAberto = false" class="text-gray-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-red-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    <span class="sr-only">Fechar</span>
                                </button>
                            </div>

                            <div class="p-0 overflow-y-auto flex-grow scrollbar-thin">
                                @if($diretoria)
                                    <div class="grid grid-cols-1 sm:grid-cols-2 h-full">
                                        
                                        @if($diretoria->image_path)
                                            <div class="h-64 sm:h-full sm:min-h-[400px] relative overflow-hidden bg-gray-100 sm:border-r border-gray-200">
                                                <img src="{{ asset('storage/' . $diretoria->image_path) }}" 
                                                    alt="Foto da {{ $church->name }}" 
                                                    class="absolute inset-0 w-full h-full object-cover">
                                            </div>
                                        @endif

                                        <div class="p-6 flex flex-col justify-center bg-white {{ !$diretoria->image_path ? 'sm:col-span-2' : '' }}">
                                            <h4 class="font-bold text-green-900 mb-6 uppercase tracking-wider text-sm flex items-center gap-2">
                                                <span class="w-2.5 h-2.5 rounded-full bg-green-500 ring-4 ring-green-100"></span>
                                                Diretoria Atual
                                            </h4>

                                            <div class="grid grid-cols-1 gap-y-5 bg-gray-50 p-6 rounded-2xl border border-gray-100 shadow-inner">
                                                
                                                @if($diretoria->president_name)
                                                    <div>
                                                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Presidente</span>
                                                        <span class="text-gray-950 font-bold text-lg leading-tight">{{ $diretoria->president_name }}</span>
                                                    </div>
                                                @endif

                                                @if($diretoria->vice_president_name)
                                                    <div>
                                                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Vice-Presidente</span>
                                                        <span class="text-gray-900 font-semibold">{{ $diretoria->vice_president_name }}</span>
                                                    </div>
                                                @endif

                                                <div class="h-px bg-gray-200 my-1"></div> @if($diretoria->first_secretary_name)
                                                    <div>
                                                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">1º Secretário(a)</span>
                                                        <span class="text-gray-900 font-semibold">{{ $diretoria->first_secretary_name }}</span>
                                                    </div>
                                                @endif

                                                @if($diretoria->second_secretary_name)
                                                    <div>
                                                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">2º Secretário(a)</span>
                                                        <span class="text-gray-900 font-semibold">{{ $diretoria->second_secretary_name }}</span>
                                                    </div>
                                                @endif

                                                @if($diretoria->executive_secretary_name)
                                                    <div>
                                                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Secretário(a) Executivo(a)</span>
                                                        <span class="text-gray-900 font-semibold">{{ $diretoria->executive_secretary_name }}</span>
                                                    </div>
                                                @endif

                                                <div class="h-px bg-gray-200 my-1"></div> @if($diretoria->treasurer_name)
                                                    <div>
                                                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Tesoureiro(a)</span>
                                                        <span class="text-gray-900 font-semibold">{{ $diretoria->treasurer_name }}</span>
                                                    </div>
                                                @endif

                                            </div>
                                        </div>
                                    </div> @else
                                    <div class="p-6 text-center py-10 flex flex-col items-center justify-center min-h-64">
                                        <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mb-4 border border-gray-200 shadow-inner">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        </div>
                                        <h4 class="text-lg font-bold text-gray-900 mb-1">Sem diretoria cadastrada</h4>
                                        <p class="text-sm text-gray-500 max-w-sm">A diretoria atual desta UMP ainda não foi atualizada no sistema pelo painel administrativo.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>



    <!-- section de eventos -->
    <div id="eventos" class="py-20 px-6 md:px-12 max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800">O que vem por aí?</h2>
            <p class="text-gray-500 mt-2">Confira nossos próximos encontros e garanta sua vaga!</p>
        </div>

        @if($events->isEmpty())
            <div class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum evento aberto</h3>
                <p class="mt-1 text-sm text-gray-500">Estamos preparando novidades. Fique ligado nas nossas redes sociais!</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                
                @foreach($events as $event)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col hover:shadow-lg transition-shadow duration-300">
                        
                        <div class="w-full h-48 relative overflow-hidden bg-gray-900 border-b border-gray-100">
                            @if($event->image)
                                <img src="{{ asset('storage/' . $event->image) }}" alt="" class="absolute inset-0 w-full h-full object-cover blur-xl opacity-60 scale-110 pointer-events-none" aria-hidden="true">
                                
                                <img src="{{ asset('storage/' . $event->image) }}" alt="{{ $event->title }}" class="relative z-10 w-full h-full object-contain p-2 hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-full bg-green-900 flex items-center justify-center">
                                    <img src="{{ asset('images/topo.png') }}" class="h-10 brightness-0 invert opacity-50" alt="FEMOPROR">
                                </div>
                            @endif
                        </div>

                        <div class="p-6 flex-grow">
                            <div class="flex justify-between items-start mb-4">
                                <span class="bg-red-100 text-red-800 text-xs font-bold px-3 py-1 rounded-full tracking-wide">
                                    {{ \Carbon\Carbon::parse($event->event_date)->format('d/m/Y H:i') }}
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
                                <div class="flex items-center text-sm text-gray-500 mb-2">
                                    <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $event->location }}
                                </div>
                            @endif
                        </div>
                        
                        <div class="p-6 pt-0 mt-auto">
                            @php
                                // Verifica se existe uma data de abertura E se a data atual ainda é anterior a ela
                                $emBreve = $event->opening_date && now()->isBefore($event->opening_date);
                            @endphp

                            @if($emBreve)
                                <button disabled class="block w-full text-center bg-gray-50 border-2 border-gray-200 text-gray-400 font-bold py-3 rounded-xl cursor-not-allowed transition-colors duration-300">
                                    <span class="flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                        Inscrições em Breve
                                    </span>
                                    <span class="block text-[10px] font-normal mt-1">
                                        Abre a {{ $event->opening_date->format('d/m/Y \à\s H:i') }}
                                    </span>
                                </button>
                            @else
                                <a href="{{ route('events.show', $event->id) }}" class="block w-full text-center bg-white border-2 border-green-900 text-green-900 hover:bg-green-900 hover:text-white font-bold py-3 rounded-xl transition-colors duration-300">
                                    Ver Detalhes / Inscrição
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
                </div>
        @endif
    </div>

    <!-- section de downloads -->
    <div id="downloads" class="py-20 px-6 md:px-12 max-w-7xl mx-auto bg-gray-50 border-t border-gray-200 mt-12">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800">Materiais e Downloads</h2>
            <p class="text-gray-500 mt-2">Documentos oficiais, manuais e arquivos úteis para a sua UMP.</p>
            <div class="w-16 h-1.5 bg-green-900 rounded-full mx-auto mt-4"></div>
        </div>

        @if($downloads->isEmpty())
            <div class="text-center py-10">
                <p class="text-gray-500">Nenhum material disponível no momento.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($downloads as $file)
                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" rel="noopener noreferrer" class="group bg-white p-6 rounded-2xl border border-gray-200 shadow-sm hover:shadow-lg hover:border-green-900 transition-all duration-300 flex items-start gap-4">
                        
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-green-900 transition-colors duration-300">
                            @svg($file->icon, 'w-6 h-6 text-green-900 group-hover:text-white')
                        </div>
                        
                        <div class="flex-grow">
                            <h3 class="font-bold text-gray-900 group-hover:text-green-900 transition-colors">{{ $file->title }}</h3>
                            @if($file->description)
                                <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $file->description }}</p>
                            @endif
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-green-700 mt-3">
                                Fazer Download
                                <svg class="w-3 h-3 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <!-- rodapé -->
    <footer class="bg-gray-100 pt-16 pb-8 border-t border-gray-200 mt-20">
        <div class="max-w-7xl mx-auto px-6 md:px-12">
            
            <div class="flex flex-col md:flex-row justify-between items-center md:items-start mb-12 gap-8 md:gap-0">
                
                <div class="text-center md:text-left flex flex-col items-center md:items-start">
                    <h3 class="text-xl font-bold text-gray-800 tracking-tight mb-2">FEMOPROR</h3>
                    <p class="text-gray-500 text-sm max-w-xs mb-4">
                        Federação de Mocidade do Presbitério Oeste Rio-Grandense.
                    </p>
                    <p class="text-gray-500 text-sm max-w-xs">
                        Alegres na esperança
                    </p>
                    <p class="text-gray-500 text-sm max-w-xs">
                        Fortes na fé
                    </p>
                    <p class="text-gray-500 text-sm max-w-xs">
                        Dedicados no amor
                    </p>
                    <p class="text-gray-500 text-sm max-w-xs mb-4">
                        Unidos no trabalho
                    </p>
                    <a href="mailto:femopror@gmail.com" class="text-green-700 hover:text-green-900 text-sm font-semibold transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        femopror@gmail.com
                    </a>
                </div>

                <div class="flex gap-4">
                    <a href="https://www.instagram.com/femopror/" target="_blank" rel="noopener noreferrer" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-500 hover:bg-[#E1306C] hover:text-white shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" />
                        </svg>
                    </a>

                    <a href="https://www.youtube.com/@FEMOPROR" target="_blank" rel="noopener noreferrer" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-500 hover:bg-[#FF0000] hover:text-white shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M19.812 5.418c.861.23 1.538.907 1.768 1.768C21.998 8.746 22 12 22 12s0 3.255-.418 4.814a2.504 2.504 0 0 1-1.768 1.768c-1.56.419-7.814.419-7.814.419s-6.255 0-7.814-.419a2.505 2.505 0 0 1-1.768-1.768C2 15.255 2 12 2 12s0-3.255.417-4.814a2.507 2.507 0 0 1 1.768-1.768C5.744 5 11.998 5 11.998 5s6.255 0 7.814.418ZM15.194 12 10 15V9l5.194 3Z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                </div>
            </div>

            <div class="pt-8 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center text-xs text-gray-400 gap-4 md:gap-0">
                <p>&copy; {{ date('Y') }} FEMOPROR. Todos os direitos reservados.</p>
                <p class="font-medium">
                    Desenvolvido por 
                    <a href="https://www.linkedin.com/in/bernardo-araujo-915a26329/" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:text-green-900 transition-colors border-b border-transparent hover:border-green-900 pb-0.5">
                        Bernardo Moura
                    </a>
                </p>
            </div>
            
        </div>
    </footer>
</div>