<?php

use App\Mail\InscricaoRecebida;
use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Support\PixPayload;
use App\Support\SafeMail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.public')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $eventId;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public $church_id = '';
    public $receipt;
    public bool $isVisitor = false;

    /** Respostas indexadas pela chave estável do campo (ver Event::customFieldDefinitions). */
    public array $respostas = [];

    public bool $mostrarPix = false;
    public string $pixCopiaCola = '';

    public function mount($id): void
    {
        $evento = Event::findOrFail($id);

        // Rascunho é evento oculto: não deve nem existir para quem está de fora.
        // Antes, `/eventos/3` abria e aceitava inscrição em qualquer status.
        abort_if($evento->status === 'draft', 404);

        $this->eventId = $evento->id;

        // Nome, e-mail, igreja e WhatsApp já vêm da conta: são os mesmos dados
        // em toda inscrição, e ficam guardados em /profile.
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->church_id = $user->church_id ?? '';
            $this->phone = $user->phone ?? '';
        }

        foreach ($evento->customFieldDefinitions() as $campo) {
            $this->respostas[$campo['key']] = $campo['type'] === 'checkbox' ? [] : '';
        }
    }

    #[Computed]
    public function event(): Event
    {
        return Event::findOrFail($this->eventId);
    }

    #[Computed]
    public function campos(): array
    {
        return $this->event->customFieldDefinitions();
    }

    #[Computed]
    public function jaInscrito(): bool
    {
        return auth()->check() && Registration::where('user_id', auth()->id())
            ->where('event_id', $this->eventId)
            ->exists();
    }

    /**
     * O total sai SEMPRE das opções cadastradas no evento, casando o rótulo
     * escolhido com o acréscimo correspondente.
     *
     * Antes o acréscimo era extraído por regex da string que o navegador
     * devolvia: como `custom_answers` é propriedade pública, dava para mandar
     * qualquer texto e forjar um valor menor, gerar o PIX com esse valor e
     * pagar a menos.
     */
    #[Computed]
    public function precoFinal(): float
    {
        $total = (float) $this->event->price;

        foreach ($this->campos as $campo) {
            $escolhido = $this->respostas[$campo['key']] ?? null;

            if (blank($escolhido)) {
                continue;
            }

            $escolhidos = is_array($escolhido) ? $escolhido : [$escolhido];

            foreach ($campo['options'] as $opcao) {
                if (in_array($opcao['label'], $escolhidos, true)) {
                    $total += $opcao['surcharge'];
                }
            }
        }

        return round($total, 2);
    }

    public function with(): array
    {
        return [
            'event' => $this->event,
            'churches' => Church::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Mudou de esporte (ou de qualquer opção que mexe no preço)? O QR já gerado
     * vale o valor antigo.
     *
     * Sem isto: a pessoa marca Futsal, gera o QR de R$ 60, marca Vôlei também,
     * a tela passa a mostrar R$ 70 — mas o QR na tela continua cobrando 60. Ela
     * paga 60, anexa o comprovante, e a tesouraria recebe um comprovante que não
     * bate com o valor da inscrição.
     */
    public function updatedRespostas(): void
    {
        $this->reset(['mostrarPix', 'pixCopiaCola']);
    }

    public function gerarPix(): void
    {
        $valor = $this->precoFinal;

        if ($valor <= 0) {
            return;
        }

        $this->pixCopiaCola = PixPayload::make($valor);
        $this->mostrarPix = true;
    }

    public function register(): void
    {
        // O formulário fica dentro de @auth, mas o método continua endereçável
        // por /livewire/update mesmo sem estar em tela nenhuma. Tirar o botão
        // da view não protege nada.
        abort_unless(auth()->check(), 403);

        $evento = $this->event;

        abort_unless($evento->acceptsRegistrations(), 403, 'As inscrições para este evento não estão abertas.');

        $chave = 'inscricao:'.auth()->id();

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $this->addError('name', 'Muitas tentativas seguidas. Aguarde '.RateLimiter::availableIn($chave).' segundos.');

            return;
        }

        if ($this->jaInscrito) {
            $this->addError('name', 'Você já está inscrito neste evento.');

            return;
        }

        // Telefone entra com máscara, DDD e traço; o banco guarda só dígitos.
        $this->phone = preg_replace('/\D/', '', (string) $this->phone) ?? '';

        $dados = $this->validate($this->regras(), $this->mensagens());

        RateLimiter::hit($chave, 300);

        $caminhoComprovante = null;

        if ($this->receipt) {
            try {
                // Disco privado: comprovante bancário não fica em endereço público.
                $caminhoComprovante = $this->receipt->store('receipts', config('femopror.uploads.disk'));
            } catch (Throwable $e) {
                report($e);
                $caminhoComprovante = false;
            }

            /*
             * Se o armazenamento falhou, a inscrição NÃO pode ser criada.
             * Sem isto, o `store()` devolvia false, a inscrição era gravada com
             * o comprovante vazio, e ninguém percebia: a pessoa via "inscrição
             * enviada com sucesso" e a tesouraria abria um registro sem anexo.
             */
            if ($caminhoComprovante === false) {
                $this->addError('receipt', 'Não conseguimos guardar seu comprovante agora. Tente de novo em instantes — sua inscrição ainda não foi registrada.');

                return;
            }
        }

        try {
            $inscricao = Registration::create([
                'event_id' => $evento->id,
                'church_id' => $dados['church_id'],
                'user_id' => auth()->id(),
                'name' => $dados['name'],
                'email' => $dados['email'],
                'phone' => $dados['phone'],
                'receipt_path' => $caminhoComprovante,
                'payment_status' => 'pending',
                // Congela quanto o sistema cobrou: é contra isso que a
                // tesouraria confere o comprovante.
                'amount_paid' => $this->precoFinal,
                'custom_answers' => $this->respostasParaRegistro(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Duplo clique ou duas abas: o índice único resolve a corrida.
            $this->addError('name', 'Você já está inscrito neste evento.');

            return;
        }

        $this->guardarNoPerfil($dados);

        // Comprovante de que o pedido chegou. Vai por SafeMail: a inscrição já
        // está gravada, e SMTP fora do ar não pode virar erro na tela de quem
        // acabou de se inscrever.
        SafeMail::send($inscricao->email, new InscricaoRecebida($inscricao));

        session()->flash('success', 'Inscrição enviada com sucesso! Mandamos um e-mail para '.$inscricao->email.' com o resumo. A diretoria irá validar o seu comprovante em breve.');

        $this->reset(['phone', 'receipt', 'isVisitor', 'mostrarPix', 'pixCopiaCola']);
        unset($this->jaInscrito);
    }

    /**
     * Quem se inscreve sem ter completado o perfil não precisa digitar igreja e
     * WhatsApp de novo na próxima vez. Só preenche o que está vazio: sobrescrever
     * o que a pessoa escolheu em /profile seria mexer no cadastro dela sem pedir.
     */
    private function guardarNoPerfil(array $dados): void
    {
        $user = auth()->user();

        $novos = array_filter([
            'church_id' => $user->church_id === null ? $dados['church_id'] : null,
            'phone' => blank($user->phone) ? $dados['phone'] : null,
        ]);

        if ($novos !== []) {
            $user->fill($novos)->save();
        }
    }

    private function regras(): array
    {
        $regras = [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'digits_between:10,11'],
            'church_id' => ['required', 'integer', 'exists:churches,id'],
            'receipt' => [
                // Evento gratuito não pede comprovante — era `required` fixo, o
                // que deixava a inscrição de graça sem saída.
                $this->event->requiresReceipt() ? 'required' : 'nullable',
                'image',
                'max:3072',
            ],
        ];

        foreach ($this->campos as $campo) {
            $chave = 'respostas.'.$campo['key'];
            $rotulos = array_column($campo['options'], 'label');

            $regras[$chave] = match ($campo['type']) {
                'checkbox' => ['array'],
                'select' => ['nullable', 'string', Rule::in($rotulos)],
                default => ['nullable', 'string', 'max:500'],
            };

            if ($campo['type'] === 'checkbox') {
                $regras[$chave.'.*'] = ['string', Rule::in($rotulos)];
            }
        }

        return $regras;
    }

    private function mensagens(): array
    {
        $mensagens = [
            'name.required' => 'Informe o seu nome completo.',
            'name.min' => 'Informe o nome completo.',
            'email.required' => 'Informe o seu e-mail.',
            'email.email' => 'Esse e-mail não parece válido.',
            'phone.required' => 'Informe o seu WhatsApp com DDD.',
            'phone.digits_between' => 'Informe o número com DDD (10 ou 11 dígitos).',
            'church_id.required' => 'Selecione a sua igreja local.',
            'church_id.exists' => 'Selecione uma igreja da lista.',
            'receipt.required' => 'Você precisa anexar o comprovante do PIX.',
            'receipt.image' => 'O comprovante precisa ser uma imagem (PNG ou JPG).',
            'receipt.max' => 'A imagem do comprovante passa de 3 MB.',
        ];

        foreach ($this->campos as $campo) {
            $mensagens['respostas.'.$campo['key'].'.in'] = 'Escolha uma das opções de "'.$campo['question'].'".';
            $mensagens['respostas.'.$campo['key'].'.*.in'] = 'Escolha uma das opções de "'.$campo['question'].'".';
        }

        return $mensagens;
    }

    /**
     * O banco guarda a resposta indexada pelo texto da pergunta (é assim que o
     * painel e o dashboard exibem). O formulário, esse, trabalha com a chave
     * estável: pergunta com ponto quebrava o wire:model, e pergunta com aspas
     * quebrava o HTML.
     */
    private function respostasParaRegistro(): array
    {
        $saida = [];

        foreach ($this->campos as $campo) {
            $saida[$campo['question']] = $this->respostas[$campo['key']] ?? ($campo['type'] === 'checkbox' ? [] : '');
        }

        return $saida;
    }
};
?>

<div class="min-h-screen bg-gray-50 py-8 sm:py-12 px-4 sm:px-6 lg:px-8">
    @push('head')
        <title>{{ $event->title }} · FEMOPROR</title>
        <meta name="description" content="{{ Str::limit(strip_tags($event->description ?? 'Inscrições abertas para '.$event->title), 155) }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $event->title }} · FEMOPROR">
        <meta property="og:description" content="{{ Str::limit(strip_tags($event->description ?? 'Inscrições abertas.'), 155) }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if($event->image)
            <meta property="og:image" content="{{ asset('storage/'.$event->image) }}">
            <meta name="twitter:card" content="summary_large_image">
        @endif
    @endpush

    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <div class="p-4 sm:p-6 bg-gray-50 border-b border-gray-100">
            <a href="{{ route('home') }}" class="text-sm font-semibold text-green-900 hover:text-green-700 flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Voltar para a Página Inicial
            </a>
        </div>

        @if (session()->has('success'))
            <div class="p-8 sm:p-12 text-center flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-green-100 text-green-800 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Inscrição Confirmada!</h2>
                <p class="text-gray-600 max-w-md mb-6">{{ session('success') }}</p>
                <a href="{{ route('dashboard') }}" class="bg-green-900 text-white px-6 py-2.5 rounded-full font-bold shadow-md hover:bg-green-800 transition">
                    Ver minhas inscrições
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2">

                <div class="p-6 sm:p-8 bg-green-950 text-white flex flex-col justify-between">
                    <div>
                        <span class="bg-white/10 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
                            {{ $event->event_date->format('d/m/Y H:i') }}
                        </span>
                        <h1 class="text-2xl font-bold mt-4 mb-2">{{ $event->title }}</h1>
                        <p class="text-green-200 text-sm leading-relaxed mb-6">{{ $event->description }}</p>

                        <div class="space-y-3 text-sm border-t border-white/10 pt-6">
                            @if($event->location)
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <span>{{ $event->location }}</span>
                                </div>
                            @endif

                            <div class="flex items-center gap-2 text-lg font-bold mt-2 text-green-400" aria-live="polite">
                                @if($this->precoFinal > 0)
                                    <span>Valor: R$ {{ number_format($this->precoFinal, 2, ',', '.') }}</span>
                                @else
                                    <span>Inscrição gratuita</span>
                                @endif
                            </div>

                            @if($this->precoFinal > 0)
                                <div class="mt-4">
                                    @if(! $mostrarPix)
                                        <button type="button" wire:click="gerarPix" wire:loading.attr="disabled" class="w-full bg-green-800 hover:bg-green-700 text-white font-bold py-3 rounded-xl border border-green-700 shadow-md transition-colors flex items-center justify-center gap-2 disabled:opacity-60">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                            Gerar QR Code PIX
                                        </button>
                                    @else
                                        <div class="bg-white rounded-2xl p-6 mt-4 shadow-lg border border-green-800 text-center text-gray-900"
                                             x-data="{ copiado: false }">
                                            <h4 class="font-bold text-green-900 uppercase tracking-wider text-sm mb-4">Escaneie para pagar</h4>

                                            <div class="bg-white p-2 rounded-xl border-2 border-gray-100 inline-block mb-4 shadow-sm">
                                                {{-- Serviço externo: se não responder, o copia-e-cola abaixo continua valendo. --}}
                                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($pixCopiaCola) }}"
                                                     alt="QR Code do PIX no valor de R$ {{ number_format($this->precoFinal, 2, ',', '.') }}"
                                                     referrerpolicy="no-referrer"
                                                     loading="lazy"
                                                     width="160" height="160"
                                                     class="w-40 h-40"
                                                     onerror="this.closest('div').innerHTML='<p class=&quot;text-xs text-gray-500 p-4&quot;>Não foi possível carregar o QR Code. Use o código abaixo.</p>'">
                                            </div>

                                            <p class="text-xs text-gray-500 font-bold mb-2">Ou use o PIX Copia e Cola:</p>

                                            <div class="flex items-center bg-gray-50 border border-gray-200 rounded-lg overflow-hidden">
                                                <input type="text" value="{{ $pixCopiaCola }}" readonly
                                                       x-ref="pix"
                                                       aria-label="Código PIX copia e cola"
                                                       class="w-full bg-transparent text-xs text-gray-600 px-3 py-2 outline-none">
                                                {{-- document.execCommand('copy') está depreciado; e alert() no meio do
                                                     pagamento é hostil no celular. --}}
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText($refs.pix.value).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })"
                                                        class="bg-green-100 hover:bg-green-200 text-green-900 px-3 py-2 text-xs font-bold transition-colors border-l border-gray-200 whitespace-nowrap">
                                                    <span x-text="copiado ? 'COPIADO!' : 'COPIAR'">COPIAR</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($this->precoFinal > 0)
                        <div class="bg-white/5 border border-white/10 rounded-xl p-5 mt-8">
                            <h3 class="font-bold text-sm uppercase tracking-wide text-green-400 mb-2">Dados para Pagamento via PIX</h3>
                            <p class="text-xs text-green-200 mb-4">Para confirmar sua inscrição, escaneie o QR-CODE ou faça o pix para a chave abaixo:</p>

                            <div class="bg-black/20 p-3 rounded-lg flex items-center justify-between border border-black/10">
                                <span class="text-xs font-mono select-all text-white">{{ config('femopror.pix.display_key') }}</span>
                                <span class="text-[10px] bg-green-500 text-black font-bold px-2 py-0.5 rounded uppercase">{{ config('femopror.pix.display_label') }}</span>
                            </div>
                            <p class="text-[11px] text-green-300 mt-2 text-center">{{ config('femopror.pix.display_owner') }}</p>
                        </div>
                    @endif
                </div>

                <div class="p-6 sm:p-8">
                    @if(! $event->acceptsRegistrations())
                        {{-- Evento encerrado ou com inscrição ainda não aberta. A URL direta
                             chegava aqui e mostrava o formulário assim mesmo. --}}
                        <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl p-10 text-center h-full flex flex-col items-center justify-center">
                            <div class="w-16 h-16 bg-white shadow-sm text-gray-400 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            @if($event->status === 'closed')
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Inscrições encerradas</h3>
                                <p class="text-gray-500 text-sm max-w-sm">As inscrições para este evento já foram fechadas. Fique de olho nos próximos!</p>
                            @else
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Inscrições em breve</h3>
                                <p class="text-gray-500 text-sm max-w-sm">
                                    Abrem em {{ $event->opening_date?->format('d/m/Y \à\s H:i') }}.
                                </p>
                            @endif
                            <a href="{{ route('home') }}" class="mt-6 px-6 py-2.5 bg-green-900 text-white font-bold rounded-full hover:bg-green-800 transition text-sm">
                                Ver outros eventos
                            </a>
                        </div>

                    @elseif($this->jaInscrito)
                        <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-8 text-center shadow-sm h-full flex flex-col justify-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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

                    @elseif($event->is_congress && ! $isVisitor)
                        <div class="h-full flex flex-col justify-center">
                            <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Escolha sua categoria</h2>
                            <p class="text-gray-500 text-center text-sm mb-8">Para prosseguir com a inscrição, identifique-se abaixo:</p>

                            <div class="grid grid-cols-1 gap-5">
                                @auth
                                    <button type="button" wire:click="$set('isVisitor', true)"
                                    class="group relative flex flex-col items-center justify-center p-6 bg-white border-2 border-green-800 rounded-2xl hover:bg-green-50 transition-all duration-300 w-full">
                                        <div class="w-12 h-12 bg-green-100 text-green-800 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        </div>
                                        <span class="text-xl font-bold text-green-950">Sou Visitante</span>
                                        <span class="text-sm text-gray-600 text-center mt-2">Inscrição individual padrão.</span>
                                    </button>
                                @else
                                    <a href="{{ route('login') }}"
                                    class="group relative flex flex-col items-center justify-center p-6 bg-white border-2 border-green-800 rounded-2xl hover:bg-green-50 transition-all duration-300 w-full">
                                        <div class="w-12 h-12 bg-gray-100 text-gray-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                        </div>
                                        <span class="text-xl font-bold text-gray-900">Sou Visitante</span>
                                        <span class="text-sm text-red-600 font-bold text-center mt-2">Login / Cadastro</span>
                                    </a>
                                @endauth

                                <a href="{{ url('/ump') }}"
                                class="group relative flex flex-col items-center justify-center p-6 bg-green-900 border-2 border-green-900 rounded-2xl hover:bg-green-950 transition-all duration-300 shadow-md w-full">
                                    <div class="w-12 h-12 bg-white/20 text-white rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    </div>
                                    <span class="text-xl font-bold text-white">Sou Delegado</span>
                                    <span class="text-sm text-green-100 text-center mt-2">Acesso exclusivo para Presidentes.</span>
                                </a>
                            </div>
                        </div>

                    @else
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Formulário de Inscrição</h2>

                            @if($event->is_congress)
                                <button type="button" wire:click="$set('isVisitor', false)" class="text-xs text-gray-500 hover:text-green-900 font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                    Voltar
                                </button>
                            @endif
                        </div>

                        @auth
                            <p class="mb-5 flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs leading-relaxed text-gray-500">
                                <svg class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 16v-4m0-3.5h.01" />
                                </svg>
                                <span>
                                    Preenchemos com os dados da sua conta. Para mudar de vez,
                                    edite o <a href="{{ route('profile.edit') }}" class="font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">seu perfil</a>.
                                </span>
                            </p>

                            <form wire:submit.prevent="register" class="space-y-5">
                                <div>
                                    <label for="campo-nome" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Nome Completo</label>
                                    {{-- O `value` explícito evita o campo aparecer vazio antes de o
                                         Livewire aplicar o estado no primeiro carregamento. --}}
                                    <input id="campo-nome" type="text" wire:model="name" value="{{ $name }}" autocomplete="name"
                                           @error('name') aria-invalid="true" @enderror
                                           class="w-full border rounded-xl px-4 py-2.5 text-sm focus:ring-1 transition outline-none @error('name') border-red-400 focus:border-red-500 focus:ring-red-500 @else border-gray-200 focus:border-green-900 focus:ring-green-900 @enderror">
                                    {{-- Sem estes blocos, falha em nome/e-mail/igreja fazia o botão
                                         simplesmente não responder, sem dizer por quê. --}}
                                    @error('name') <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="campo-email" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">E-mail</label>
                                    <input id="campo-email" type="email" wire:model="email" value="{{ $email }}" autocomplete="email"
                                           class="w-full border rounded-xl px-4 py-2.5 text-sm focus:ring-1 transition outline-none @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @else border-gray-200 focus:border-green-900 focus:ring-green-900 @enderror">
                                    @error('email') <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="campo-telefone" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                        WhatsApp / Telefone
                                    </label>
                                    {{-- maxlength era 11 e cortava o número de quem digitava com
                                         máscara. Agora o servidor normaliza para dígitos. --}}
                                    <input id="campo-telefone" type="tel"
                                        wire:model="phone"
                                        value="{{ $phone }}"
                                        inputmode="numeric"
                                        maxlength="16"
                                        autocomplete="tel"
                                        placeholder="(84) 99999-9999"
                                        class="w-full border rounded-xl px-4 py-2.5 text-sm focus:ring-1 transition outline-none @error('phone') border-red-400 focus:border-red-500 focus:ring-red-500 @else border-gray-200 focus:border-green-900 focus:ring-green-900 @enderror">

                                    @error('phone') <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="campo-igreja" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Sua Igreja Local</label>
                                    <select id="campo-igreja" wire:model="church_id"
                                            class="w-full border rounded-xl px-4 py-2.5 text-sm focus:ring-1 transition outline-none bg-white @error('church_id') border-red-400 focus:border-red-500 focus:ring-red-500 @else border-gray-200 focus:border-green-900 focus:ring-green-900 @enderror">
                                        <option value="">Selecione sua igreja...</option>
                                        @foreach($churches as $church)
                                            <option value="{{ $church->id }}" @selected($church_id == $church->id)>{{ $church->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('church_id') <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                @if(count($this->campos) > 0)
                                    <div class="pt-4 mt-4 border-t border-gray-100">
                                        <h3 class="text-sm font-bold text-green-900 mb-4 uppercase tracking-wider">Informações Adicionais</h3>

                                        @foreach($this->campos as $campo)
                                            <fieldset class="mb-5" wire:key="campo-{{ $campo['key'] }}">
                                                <legend class="block text-xs font-bold text-gray-700 mb-2">{{ $campo['question'] }}</legend>

                                                @if($campo['type'] === 'checkbox')
                                                    <div class="space-y-2">
                                                        @foreach($campo['options'] as $opcao)
                                                            <label class="flex items-center gap-3 cursor-pointer p-3 border border-gray-200 rounded-xl hover:bg-green-50 hover:border-green-300 transition-all bg-white shadow-sm">
                                                                <input type="checkbox"
                                                                    wire:model.live="respostas.{{ $campo['key'] }}"
                                                                    value="{{ $opcao['label'] }}"
                                                                    class="w-5 h-5 rounded border-gray-300 text-green-900 focus:ring-green-900 transition">
                                                                <span class="text-sm font-medium text-gray-700">{{ $opcao['label'] }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>

                                                @elseif($campo['type'] === 'select')
                                                    <select wire:model.live="respostas.{{ $campo['key'] }}" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                                        <option value="">Selecione...</option>
                                                        @foreach($campo['options'] as $opcao)
                                                            <option value="{{ $opcao['label'] }}">{{ $opcao['label'] }}</option>
                                                        @endforeach
                                                    </select>

                                                @else
                                                    <input type="text" wire:model.blur="respostas.{{ $campo['key'] }}" maxlength="500" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                                @endif

                                                @error('respostas.'.$campo['key']) <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                            </fieldset>
                                        @endforeach
                                    </div>
                                @endif

                                @if($event->requiresReceipt())
                                    <div class="border-2 border-dashed rounded-xl p-4 bg-gray-50 text-center relative mt-4 @error('receipt') border-red-300 @else border-gray-200 @enderror">
                                        <label class="cursor-pointer block">
                                            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                            <span class="text-xs font-semibold text-green-900 block">Anexar Comprovante do PIX</span>
                                            <span class="text-[10px] text-gray-400">Clique para selecionar (PNG ou JPG, até 3 MB)</span>
                                            <input type="file" wire:model="receipt" class="sr-only" accept="image/png,image/jpeg">
                                        </label>

                                        @if ($receipt)
                                            <div class="mt-2 text-xs font-bold text-green-700 flex items-center justify-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                Imagem carregada!
                                            </div>
                                        @endif
                                        <div wire:loading wire:target="receipt" class="text-xs text-gray-500 font-semibold mt-2">Enviando arquivo...</div>
                                        @error('receipt') <span class="text-red-600 text-xs mt-1 block font-medium text-left">{{ $message }}</span> @enderror
                                    </div>
                                @endif

                                <button type="submit" wire:loading.attr="disabled" wire:target="register" class="w-full bg-green-900 text-white font-bold py-3 rounded-xl shadow-md hover:bg-green-800 transition disabled:opacity-50 flex items-center justify-center gap-2">
                                    <span wire:loading.remove wire:target="register">Finalizar Minha Inscrição</span>
                                    <span wire:loading wire:target="register">Processando inscrição...</span>
                                </button>
                            </form>
                        @else
                            @php($voltarPara = route('events.show', $event->id, absolute: false))

                            <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl p-8 sm:p-10 text-center flex flex-col items-center justify-center h-full">
                                <div class="w-16 h-16 bg-white shadow-sm text-gray-400 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>

                                <h3 class="text-xl font-bold text-gray-900 mb-2">Crie sua conta para se inscrever</h3>
                                <p class="text-gray-500 text-sm mb-6 max-w-sm">
                                    É rápido: nome, e-mail e senha. A conta é o que te deixa acompanhar a
                                    confirmação do pagamento depois.
                                </p>

                                {{-- Os dois caminhos levam o `redirect` de volta para este evento.
                                     Antes havia um botão só, apontando para /login sem destino:
                                     quem entrava caía no dashboard e tinha que achar o evento de novo. --}}
                                <div class="w-full max-w-xs space-y-3">
                                    <a href="{{ route('register', ['redirect' => $voltarPara]) }}"
                                       class="block w-full px-8 py-3 bg-green-900 text-white font-bold rounded-xl shadow-md hover:bg-green-800 transition text-center">
                                        Criar minha conta
                                    </a>

                                    <a href="{{ route('login', ['redirect' => $voltarPara]) }}"
                                       class="block w-full px-8 py-3 bg-white border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition text-center">
                                        Já tenho conta
                                    </a>
                                </div>

                                <p class="mt-5 text-xs text-gray-400 max-w-xs">
                                    Você volta para esta página assim que terminar.
                                </p>
                            </div>
                        @endauth
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
