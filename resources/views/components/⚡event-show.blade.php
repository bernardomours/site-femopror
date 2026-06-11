<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Event;
use App\Models\Church;
use App\Models\Registration;
use Livewire\WithFileUploads;

new #[Layout('layouts.public')] class extends Component {
    use WithFileUploads;

    public $eventId;
    public $event;

    public $name;
    public $email;
    public $phone;
    public $church_id;
    public $receipt;
    public $isVisitor = false;

    public $custom_answers = [];

    public function mount($id)
    {
        $this->eventId = $id;
        $this->event = Event::findOrFail($id);

        if (auth()->check()) {
            $this->name = auth()->user()->name;
            $this->email = auth()->user()->email;
        }

        if (!empty($this->event->custom_fields)) {
            foreach ($this->event->custom_fields as $field) {
                if (isset($field['type']) && $field['type'] === 'checkbox') {
                    $this->custom_answers[$field['question']] = [];
                } else {
                    $this->custom_answers[$field['question']] = '';
                }
            }
        }
    }

    public function with(): array
    {
        return [
            'churches' => Church::orderBy('name', 'asc')->get(),
        ];
    }

    public function register()
    {
        $this->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|min:10|max:20',
            'church_id' => 'required|exists:churches,id',
            'receipt' => 'required|image|max:3072',
        ], [
            'name.required' => 'O campo nome é obrigatório.',
            'email.required' => 'O campo e-mail é obrigatório.',
            'phone.required' => 'O campo telefone/whatsapp é obrigatório.',
            'church_id.required' => 'Selecione a sua igreja local.',
            'receipt.required' => 'Você precisa anexar o comprovante do PIX.',
        ]);

        $receiptPath = $this->receipt->store('receipts', 'public');

        Registration::create([
            'event_id' => $this->eventId,
            'church_id' => $this->church_id,
            'user_id' => auth()->id(),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'receipt_path' => $receiptPath,
            'payment_status' => 'pending',
            'custom_answers' => $this->custom_answers,
        ]);

        session()->flash('success', 'Inscrição enviada com sucesso! A diretoria irá validar o seu comprovante em breve.');

        $this->reset(['name', 'email', 'phone', 'church_id', 'receipt', 'custom_answers', 'isVisitor']);
    }

    public function getFinalPrice()
    {
        $total = (float) $this->event->price;

        if (empty($this->custom_answers)) {
            return $total;
        }

        foreach ($this->custom_answers as $resposta) {
            
            if (is_array($resposta)) {
                foreach ($resposta as $item) {
                    if (preg_match('/\(\+[^0-9]*(\d+[.,]?\d*)[^)]*\)/', $item, $matches)) {
                        $valorAdicional = (float) str_replace(',', '.', $matches[1]);
                        $total += $valorAdicional;
                    }
                }
            } 
            else {
                if (preg_match('/\(\+[^0-9]*(\d+[.,]?\d*)[^)]*\)/', $resposta, $matches)) {
                    $valorAdicional = (float) str_replace(',', '.', $matches[1]);
                    $total += $valorAdicional;
                }
            }
        }

        return $total;
    }
};
?>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        
        <div class="p-6 bg-gray-50 border-b border-gray-100">
            <a href="/" class="text-sm font-semibold text-green-900 hover:text-green-700 flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Voltar para a Página Inicial
            </a>
        </div>

        @if (session()->has('success'))
            <div class="p-12 text-center flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-green-100 text-green-800 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Inscrição Confirmada!</h2>
                <p class="text-gray-600 max-w-md mb-6">{{ session('success') }}</p>
                <a href="/" class="bg-green-900 text-white px-6 py-2.5 rounded-full font-bold shadow-md hover:bg-green-800 transition">
                    Voltar ao Portal
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2">
                
                <div class="p-8 bg-green-950 text-white flex flex-col justify-between">
                    <div>
                        <span class="bg-white/10 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
                            {{ \Carbon\Carbon::parse($event->event_date)->format('d/m/Y H:i') }}
                        </span>
                        <h1 class="text-2xl font-bold mt-4 mb-2">{{ $event->title }}</h1>
                        <p class="text-green-200 text-sm leading-relaxed mb-6">{{ $event->description }}</p>

                        <div class="space-y-3 text-sm border-t border-white/10 pt-6">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span>{{ $event->location }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-lg font-bold mt-2 text-green-400">
                                <span>Valor: R$ {{ number_format($this->getFinalPrice(), 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-xl p-5 mt-8">
                        <h3 class="font-bold text-sm uppercase tracking-wide text-green-400 mb-2">Dados para Pagamento via PIX</h3>
                        <p class="text-xs text-green-200 mb-4">Para confirmar sua inscrição, faça o pix para a chave abaixo:</p>
                        
                        <div class="bg-black/20 p-3 rounded-lg flex items-center justify-between border border-black/10">
                            <span class="text-xs font-mono select-all text-white">84991350289</span>
                            <span class="text-[10px] bg-green-500 text-black font-bold px-2 py-0.5 rounded uppercase">Celular</span>
                        </div>
                        <p class="text-[11px] text-green-300 mt-2 text-center">Federação de Mocidades do PROR / Adson (Tesouraria)</p>
                    </div>
                </div>

                <div class="p-8">
                    @php
                        $isCongress = str_contains(strtolower($event->title), 'congresso');
                        $jaInscrito = false;
                        
                        if (auth()->check()) {
                            $jaInscrito = \App\Models\Registration::where('user_id', auth()->id())
                                        ->where('event_id', $event->id)
                                        ->exists();
                        }
                    @endphp

                    @if($jaInscrito)
                        <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-8 text-center shadow-sm h-full flex flex-col justify-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            
                            <h3 class="text-2xl font-bold text-gray-900 mb-2 uppercase tracking-tight">Você já está inscrito!</h3>
                            
                            <p class="text-gray-600 mb-6 text-sm">
                                Sua inscrição para este evento já foi registrada. Acompanhe o status diretamente na sua área exclusiva.
                            </p>
                            
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-900 text-white font-bold rounded-full hover:bg-green-800 transition-colors shadow-md">
                                Acessar Meu Painel
                            </a>
                        </div>
                    @else
                        @if($isCongress && !$isVisitor)
                            <div class="h-full flex flex-col justify-center">
                                <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Escolha sua categoria</h2>
                                <p class="text-gray-500 text-center text-sm mb-8">Para prosseguir com a inscrição, identifique-se abaixo:</p>
                                
                                <div class="grid grid-cols-1 gap-5">
                                    @auth
                                        <button type="button" wire:click="$set('isVisitor', true)" 
                                        class="group relative flex flex-col items-center justify-center p-6 bg-white border-2 border-green-800 rounded-2xl hover:bg-green-50 transition-all duration-300 w-full">
                                            <div class="w-12 h-12 bg-green-100 text-green-800 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            </div>
                                            <span class="text-xl font-bold text-green-950">Sou Visitante</span>
                                            <span class="text-sm text-gray-600 text-center mt-2">Inscrição individual padrão.</span>
                                        </button>
                                    @else
                                        <a href="{{ route('login') }}" 
                                        class="group relative flex flex-col items-center justify-center p-6 bg-white border-2 border-green-800 rounded-2xl hover:bg-green-50 transition-all duration-300 w-full">
                                            <div class="w-12 h-12 bg-gray-100 text-gray-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            </div>
                                            <span class="text-xl font-bold text-gray-900">Sou Visitante</span>
                                            <span class="text-sm text-red-600 font-bold text-center mt-2">Login / Cadastro</span>
                                        </a>
                                    @endauth

                                    <a href="{{ url('/ump') }}" 
                                    class="group relative flex flex-col items-center justify-center p-6 bg-green-900 border-2 border-green-900 rounded-2xl hover:bg-green-950 transition-all duration-300 shadow-md w-full">
                                        <div class="w-12 h-12 bg-white/20 text-white rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        </div>
                                        <span class="text-xl font-bold text-white">Sou Delegado</span>
                                        <span class="text-sm text-green-100 text-center mt-2">Acesso exclusivo para Presidentes.</span>
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-bold text-gray-900">Formulário de Inscrição</h2>
                                
                                @if($isCongress)
                                    <button type="button" wire:click="$set('isVisitor', false)" class="text-xs text-gray-500 hover:text-green-900 font-bold flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                        Voltar
                                    </button>
                                @endif
                            </div>
                                @auth
                                <form wire:submit.prevent="register" class="space-y-5">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Nome Completo</label>
                                        <input type="text" wire:model="name" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">E-mail</label>
                                        <input type="email" wire:model="email" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                            WhatsApp / Telefone
                                        </label>
                                        <input type="text" 
                                            wire:model="phone" 
                                            maxlength="11"
                                            placeholder="84999999999" 
                                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                            
                                        @error('phone') 
                                            <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> 
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Sua Igreja Local</label>
                                        <select wire:model="church_id" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none bg-white">
                                            <option value="">Selecione sua igreja...</option>
                                            @foreach($churches as $church)
                                                <option value="{{ $church->id }}">{{ $church->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    @if(!empty($event->custom_fields))
                                        <div class="pt-4 mt-4 border-t border-gray-100">
                                            <h3 class="text-sm font-bold text-green-900 mb-4 uppercase tracking-wider">Informações Adicionais</h3>
                                            
                                            @foreach($event->custom_fields as $q)
                                                <div class="mb-5">
                                                    <label class="block text-xs font-bold text-gray-700 mb-2">{{ $q['question'] ?? 'Pergunta' }}</label>
                                                    
                                                    @if(isset($q['type']) && $q['type'] === 'checkbox')
                                                        <div class="space-y-2">
                                                            @if(!empty($q['options']))
                                                                @foreach(explode(',', $q['options']) as $option)
                                                                    <label class="flex items-center gap-3 cursor-pointer p-3 border border-gray-200 rounded-xl hover:bg-green-50 hover:border-green-300 transition-all bg-white shadow-sm">
                                                                        <input type="checkbox" 
                                                                            wire:model.live="custom_answers.{{ $q['question'] }}" 
                                                                            value="{{ trim($option) }}" 
                                                                            class="w-5 h-5 rounded border-gray-300 text-green-900 focus:ring-green-900 transition">
                                                                        <span class="text-sm font-medium text-gray-700">{{ trim($option) }}</span>
                                                                    </label>
                                                                @endforeach
                                                            @endif
                                                        </div>

                                                    @elseif(isset($q['type']) && $q['type'] === 'select')
                                                        <select wire:model.live="custom_answers.{{ $q['question'] }}" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                                            <option value="">Selecione...</option>
                                                            @if(!empty($q['options']))
                                                                @foreach(explode(',', $q['options']) as $option)
                                                                    <option value="{{ trim($option) }}">{{ trim($option) }}</option>
                                                                @endforeach
                                                            @endif
                                                        </select>

                                                    @else
                                                        <input type="text" wire:model="custom_answers.{{ $q['question'] }}" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 bg-gray-50 text-center relative mt-4">
                                        <label class="cursor-pointer block">
                                            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                            <span class="text-xs font-semibold text-green-900 block">Anexar Comprovante do PIX</span>
                                            <span class="text-[10px] text-gray-400">Clique para selecionar ou arraste o arquivo (PNG, JPG)</span>
                                            <input type="file" wire:model="receipt" class="hidden" accept="image/*">
                                        </label>

                                        @if ($receipt)
                                            <div class="mt-2 text-xs font-bold text-green-700 flex items-center justify-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                Imagem carregada!
                                            </div>
                                        @endif
                                        <div wire:loading wire:target="receipt" class="text-xs text-gray-500 font-semibold mt-2">Enviando arquivo...</div>
                                        @error('receipt') <span class="text-red-600 text-xs mt-1 block font-medium text-left">{{ $message }}</span> @enderror
                                    </div>

                                    <button type="submit" wire:loading.attr="disabled" class="w-full bg-green-900 text-white font-bold py-3 rounded-xl shadow-md hover:bg-green-800 transition disabled:opacity-50 flex items-center justify-center gap-2">
                                        <span wire:loading.remove wire:target="register">Finalizar Minha Inscrição</span>
                                        <span wire:loading wire:target="register">Processando inscrição...</span>
                                    </button>
                                </form>
                                @else
                                <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl p-10 text-center flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-white shadow-sm text-gray-400 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-gray-900 mb-2">Identificação Necessária</h3>
                                    <p class="text-gray-500 text-sm mb-6 max-w-sm">
                                        Para realizar sua inscrição e acompanhar o status do seu pagamento posteriormente, você precisa acessar sua conta.
                                    </p>
                                    
                                    <a href="{{ route('login') }}" class="w-full sm:w-auto px-8 py-3 bg-green-900 text-white font-bold rounded-xl shadow-md hover:bg-green-800 transition text-center inline-block">
                                        Fazer Login ou Cadastrar
                                    </a>
                                </div>
                                @endauth
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>