<?php

use App\Mail\InscricaoRecebida;
use App\Models\Church;
use App\Models\Event;
use App\Models\Registration;
use App\Support\PixPayload;
use App\Support\SafeMail;
use App\Support\UploadLimit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * A inscrição acontece em duas etapas:
 *
 *   ① Seus dados   → valida e SALVA a inscrição, com o valor congelado
 *   ② Pagamento    → mostra o PIX desse valor e recebe o comprovante
 *
 * A inscrição é salva ANTES do pagamento de propósito. Pagar pelo celular é
 * sair do navegador e abrir o app do banco — e o navegador muitas vezes
 * descarta a aba que ficou para trás. Se nada estivesse salvo, a pessoa pagava,
 * voltava para um formulário vazio, e a tesouraria recebia um PIX sem inscrição.
 *
 * Por isso a tela não guarda "em que etapa estou" numa propriedade: ela
 * pergunta ao banco. Tem inscrição esperando comprovante? Etapa 2. Reabrir a
 * página, trocar de aparelho ou voltar pelo e-mail cai no lugar certo sozinho.
 */
new #[Layout('layouts.public')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $eventId;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public $church_id = '';
    public $receipt;

    /*
     * `$isVisitor` saiu junto com a tela "Escolha sua categoria": ela era a
     * única coisa que lia essa propriedade. Hoje a categoria é uma resposta do
     * formulário, gravada em `custom_answers` como qualquer outra — e, por ser
     * resposta, fica validada contra a lista de opções do evento, o que uma
     * propriedade pública booleana nunca esteve.
     */

    /** Voltou da etapa de pagamento para alterar os dados. */
    public bool $editando = false;

    /** Está trocando um comprovante que já havia sido enviado. */
    public bool $substituindoComprovante = false;

    /** Respostas indexadas pela chave estável do campo (ver Event::customFieldDefinitions). */
    public array $respostas = [];

    public function mount($id): void
    {
        $evento = Event::findOrFail($id);

        // Rascunho é evento oculto: não deve nem existir para quem está de fora.
        abort_if($evento->status === 'draft', 404);

        $this->eventId = $evento->id;

        foreach ($evento->customFieldDefinitions() as $campo) {
            $this->respostas[$campo['key']] = $campo['type'] === 'checkbox' ? [] : '';
        }

        if (! $user = auth()->user()) {
            return;
        }

        $inscricao = Registration::where('user_id', $user->id)->where('event_id', $evento->id)->first();

        if ($inscricao) {
            $this->preencherComInscricao($inscricao);

            return;
        }

        // Sem inscrição ainda: nome, e-mail, igreja e WhatsApp vêm da conta.
        $this->name = $user->name;
        $this->email = $user->email;
        $this->church_id = $user->church_id ?? '';
        $this->phone = $user->phone ?? '';
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

    /**
     * As páginas do formulário: "Seus dados" mais as que a diretoria cadastrou.
     *
     * @return array<int, array{title: string, description: string}>
     */
    #[Computed]
    public function paginas(): array
    {
        return $this->event->formPages();
    }

    /** As perguntas de uma página. */
    public function camposDaPagina(int $indice): array
    {
        return array_values(array_filter($this->campos, fn (array $c) => $c['page'] === $indice));
    }

    /**
     * Em que página o preenchimento está.
     *
     * Diferente da etapa macro (dados × pagamento), **esta** é propriedade e
     * não vem do banco — de propósito. Até salvar, não existe inscrição para
     * derivar nada: se a aba morrer aqui, a pessoa recomeça o formulário, que
     * é o que já acontecia quando ele era uma tela só. O que não pode morrer é
     * o que já foi gravado, e isso segue derivado da inscrição.
     */
    public int $pagina = 0;

    public function irParaPagina(int $indice): void
    {
        // Só para trás pelo clique no passo: avançar tem que passar pela
        // validação da página atual, senão o obrigatório deixa de ser obrigatório.
        if ($indice >= 0 && $indice < $this->pagina) {
            $this->pagina = $indice;
        }
    }

    public function proximaPagina(): void
    {
        $this->validate($this->regrasDaPagina($this->pagina), $this->mensagens());

        $this->pagina = min($this->pagina + 1, count($this->paginas) - 1);
    }

    public function paginaAnterior(): void
    {
        $this->pagina = max($this->pagina - 1, 0);
    }

    /**
     * As regras só da página atual.
     *
     * Validar o formulário inteiro a cada avanço mostraria erro de campo que a
     * pessoa ainda nem viu. Validar nada deixaria o obrigatório furado — por
     * isso `salvarDados()` revalida tudo no fim, que é onde a gravação
     * acontece.
     */
    private function regrasDaPagina(int $indice): array
    {
        $todas = $this->regras();

        // A primeira página carrega os campos fixos (nome, e-mail, WhatsApp,
        // igreja); as demais, só as próprias perguntas.
        $regras = $indice === 0
            ? array_filter($todas, fn (string $chave) => ! str_starts_with($chave, 'respostas.'), ARRAY_FILTER_USE_KEY)
            : [];

        foreach ($this->camposDaPagina($indice) as $campo) {
            $chave = 'respostas.'.$campo['key'];

            foreach ($todas as $nome => $regra) {
                if ($nome === $chave || str_starts_with($nome, $chave.'.')) {
                    $regras[$nome] = $regra;
                }
            }
        }

        return $regras;
    }

    /**
     * A inscrição desta pessoa neste evento. Sempre pelo usuário autenticado —
     * nenhum id vem do navegador, então não há como mexer na inscrição alheia.
     */
    #[Computed]
    public function inscricao(): ?Registration
    {
        if (! auth()->check()) {
            return null;
        }

        return Registration::with(['event', 'church'])
            ->where('user_id', auth()->id())
            ->where('event_id', $this->eventId)
            ->first();
    }

    /**
     * O total da etapa 1, recalculado enquanto a pessoa escolhe.
     *
     * Sai SEMPRE das opções cadastradas no evento, casando o rótulo escolhido
     * com o acréscimo correspondente. Antes o acréscimo era extraído por regex
     * da string que o navegador devolvia: dava para forjar um valor menor.
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

    /**
     * O PIX sai do valor SALVO na inscrição, nunca do que está na tela.
     *
     * Na versão anterior o QR ficava ao lado do formulário e podia ser gerado a
     * qualquer momento: a pessoa gerava o de R$ 55, marcava mais uma modalidade,
     * e o QR continuava cobrando 55. Aqui não há como envelhecer — mudar a
     * escolha exige voltar à etapa 1 e salvar de novo.
     */
    #[Computed]
    public function pixCopiaCola(): string
    {
        $valor = $this->inscricao?->valorCobrado() ?? 0;

        return $valor > 0 ? PixPayload::make($valor) : '';
    }

    public function with(): array
    {
        return [
            'event' => $this->event,
            'churches' => Church::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Etapa 1 → valida e salva. Com valor a pagar, a tela passa para o
     * pagamento; sem valor, a inscrição termina aqui.
     */
    public function salvarDados(): void
    {
        // O formulário fica dentro de @auth, mas o método continua endereçável
        // por /livewire/update mesmo sem estar em tela nenhuma.
        abort_unless(auth()->check(), 403);

        $evento = $this->event;

        abort_unless($evento->acceptsRegistrations(), 403, 'As inscrições para este evento não estão abertas.');

        $existente = $this->inscricao;

        // Inscrição com comprovante (ou já confirmada) não volta a ser editada:
        // mudar a modalidade mudaria o valor de um PIX que já foi pago.
        if ($existente && ! $existente->isAwaitingReceipt()) {
            $this->editando = false;
            $this->addError('name', 'Você já está inscrito neste evento.');

            return;
        }

        if ($this->limiteEstourado('name')) {
            return;
        }

        // Telefone entra com máscara, DDD e traço; o banco guarda só dígitos.
        $this->phone = preg_replace('/\D/', '', (string) $this->phone) ?? '';

        $dados = $this->validate($this->regras(), $this->mensagens());

        RateLimiter::hit($this->chaveDoLimite(), 300);

        $atributos = [
            'church_id' => $dados['church_id'],
            'name' => $dados['name'],
            'email' => $dados['email'],
            'phone' => $dados['phone'],
            // Congela quanto o sistema cobrou: é contra isso que a tesouraria
            // confere o comprovante, e é este o valor do QR da etapa 2.
            'amount_paid' => $this->precoFinal,
            'custom_answers' => $this->respostasParaRegistro(),
        ];

        try {
            $inscricao = $existente
                ? tap($existente)->update($atributos)
                : Registration::create($atributos + [
                    'event_id' => $evento->id,
                    'user_id' => auth()->id(),
                    'payment_status' => 'pending',
                    'receipt_path' => null,
                ]);
        } catch (UniqueConstraintViolationException) {
            // Duplo clique ou duas abas: o índice único resolve a corrida.
            $this->addError('name', 'Você já está inscrito neste evento.');

            return;
        }

        $this->guardarNoPerfil($dados);
        $this->editando = false;
        unset($this->inscricao);

        $inscricao->load('event');

        // Ainda falta o comprovante: a tela vira a etapa de pagamento.
        if ($inscricao->isAwaitingReceipt()) {
            return;
        }

        // Nada mais a fazer por parte da pessoa: é aqui que ela recebe o resumo.
        SafeMail::send($inscricao->email, new InscricaoRecebida($inscricao));

        if ($inscricao->valorCobrado() <= 0) {
            session()->flash('success_titulo', 'Inscrição confirmada!');
            session()->flash('success', 'Sua vaga está garantida. Mandamos um e-mail para '.$inscricao->email.' com o resumo.');
        }
    }

    /**
     * Etapa 2 → anexa o comprovante à inscrição já salva, ou substitui o que
     * já está lá.
     *
     * Trocar vale enquanto a tesouraria não confirmou o pagamento: quem mandou
     * o print errado precisava falar com a diretoria, porque a tela não dava
     * nenhum caminho de volta.
     */
    public function enviarComprovante(): void
    {
        abort_unless(auth()->check(), 403);

        $inscricao = $this->inscricao;

        // Só a própria inscrição. De propósito NÃO exige inscrições abertas:
        // quem salvou antes de fecharem ainda precisa conseguir pagar.
        abort_unless(
            $inscricao && ($inscricao->isAwaitingReceipt() || $inscricao->canReplaceReceipt()),
            403,
        );

        if ($this->limiteEstourado('receipt')) {
            return;
        }

        $this->validate([
            // heic/heif entram porque é o formato padrão da câmera do iPhone:
            // sem eles, metade do público não conseguia mandar a própria foto.
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,heic,heif', 'max:'.UploadLimit::maxKilobytes()],
        ], $this->mensagens());

        RateLimiter::hit($this->chaveDoLimite(), 300);

        $disco = config('femopror.uploads.disk');
        $antigo = $inscricao->receipt_path;

        try {
            // Disco privado: comprovante bancário não fica em endereço público.
            $caminho = $this->receipt->store('receipts', $disco);
        } catch (Throwable $e) {
            report($e);
            $caminho = false;
        }

        // Falha de armazenamento NÃO pode marcar a inscrição como enviada:
        // a pessoa veria "sucesso" e a tesouraria abriria um registro sem anexo.
        if (! $caminho) {
            $this->addError('receipt', 'Não conseguimos guardar seu comprovante agora. Tente de novo em instantes — sua inscrição continua salva.');

            return;
        }

        /*
         * A gravação confirma o estado que a tela viu: ainda pendente e com o
         * mesmo arquivo de antes. Duas abas enviando ao mesmo tempo, ou uma
         * troca depois de a tesouraria já ter confirmado, param aqui.
         */
        $gravou = Registration::whereKey($inscricao->getKey())
            ->where('payment_status', 'pending')
            ->where(fn ($q) => blank($antigo) ? $q->whereNull('receipt_path') : $q->where('receipt_path', $antigo))
            ->update(['receipt_path' => $caminho]);

        if ($gravou === 0) {
            Storage::disk($disco)->delete($caminho);
            $this->reset('receipt', 'substituindoComprovante');
            unset($this->inscricao);

            return;
        }

        // O arquivo trocado sai do armazenamento: documento bancário sem dono
        // ocupando espaço pago não serve para nada.
        if (filled($antigo)) {
            Storage::disk($disco)->delete($antigo);
        }

        $inscricao->refresh()->load(['event', 'church']);

        if (blank($antigo)) {
            SafeMail::send($inscricao->email, new InscricaoRecebida($inscricao));

            session()->flash('success_titulo', 'Inscrição enviada!');
            session()->flash('success', 'Recebemos seu comprovante. Mandamos um e-mail para '.$inscricao->email.' com o resumo, e outro chega assim que a tesouraria confirmar o pagamento.');
        } else {
            // Trocar arquivo não é inscrição nova: e-mail de novo só seria ruído.
            session()->flash('comprovante_trocado', 'Comprovante substituído. A tesouraria vai conferir o novo arquivo.');
        }

        $this->reset('receipt', 'substituindoComprovante');
        unset($this->inscricao);
    }

    /** Abre o campo de upload em cima de um comprovante já enviado. */
    public function trocarComprovante(): void
    {
        abort_unless(auth()->check(), 403);

        $inscricao = $this->inscricao;

        abort_unless($inscricao && $inscricao->canReplaceReceipt(), 403);

        $this->reset('receipt');
        $this->resetErrorBag();
        $this->substituindoComprovante = true;
    }

    public function cancelarTrocaComprovante(): void
    {
        $this->reset('receipt', 'substituindoComprovante');
        $this->resetErrorBag();
    }

    /** Da etapa 2 de volta para a 1, para trocar modalidade ou corrigir dados. */
    public function alterarDados(): void
    {
        abort_unless(auth()->check(), 403);

        $inscricao = $this->inscricao;

        abort_unless($inscricao && $inscricao->isAwaitingReceipt(), 403);
        abort_unless($this->event->acceptsRegistrations(), 403, 'As inscrições para este evento não estão abertas.');

        $this->preencherComInscricao($inscricao);
        $this->resetErrorBag();
        $this->editando = true;
        // A edição é tela única; voltar com a paginação a meio caminho
        // deixaria o stepper apontando para um passo que não está em tela.
        $this->pagina = 0;
    }

    public function cancelarAlteracao(): void
    {
        // Descarta o que foi mexido e não salvo: a etapa 2 mostra o que está
        // gravado, e o formulário não pode ficar dizendo outra coisa.
        if ($inscricao = $this->inscricao) {
            $this->preencherComInscricao($inscricao);
        }

        $this->resetErrorBag();
        $this->editando = false;
    }

    private function preencherComInscricao(Registration $inscricao): void
    {
        $this->name = (string) $inscricao->name;
        $this->email = (string) $inscricao->email;
        $this->phone = (string) $inscricao->phone;
        $this->church_id = $inscricao->church_id ?? '';

        $salvas = (array) ($inscricao->custom_answers ?? []);

        foreach ($this->campos as $campo) {
            $valor = $salvas[$campo['question']] ?? null;

            $this->respostas[$campo['key']] = $campo['type'] === 'checkbox'
                ? array_values((array) ($valor ?? []))
                : (string) ($valor ?? '');
        }
    }

    private function chaveDoLimite(): string
    {
        return 'inscricao:'.auth()->id();
    }

    private function limiteEstourado(string $campo): bool
    {
        if (! RateLimiter::tooManyAttempts($this->chaveDoLimite(), 10)) {
            return false;
        }

        $this->addError($campo, 'Muitas tentativas seguidas. Aguarde '.RateLimiter::availableIn($this->chaveDoLimite()).' segundos.');

        return true;
    }

    /**
     * Quem se inscreve sem ter completado o perfil não precisa digitar igreja e
     * WhatsApp de novo na próxima vez. Só preenche o que está vazio.
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
        ];

        foreach ($this->campos as $campo) {
            $chave = 'respostas.'.$campo['key'];
            $rotulos = array_column($campo['options'], 'label');

            // Obrigatória é escolha de quem cadastrou o evento; o padrão
            // continua opcional, para não mudar evento que já existe.
            $exigida = $campo['required'] ?? false;

            $regras[$chave] = match ($campo['type']) {
                // Checkbox vazio chega como array vazio, não como null:
                // `required` não pega isso, `min:1` pega.
                'checkbox' => $exigida ? ['array', 'min:1'] : ['array'],
                'select', 'radio' => [$exigida ? 'required' : 'nullable', 'string', Rule::in($rotulos)],
                'textarea' => [$exigida ? 'required' : 'nullable', 'string', 'max:2000'],
                /*
                 * Campo de arquivo tem dois estados, e a regra muda conforme
                 * o que está em mãos: arquivo recém-escolhido é validado como
                 * arquivo; depois de gravado, o que resta na propriedade é o
                 * **caminho** (string), e aplicar `file` nele reprovaria quem
                 * só voltou para alterar outro dado.
                 */
                'file' => data_get($this->respostas, $campo['key']) instanceof UploadedFile
                    ? ['file', 'mimes:jpg,jpeg,png,pdf,heic,heif', 'max:'.UploadLimit::maxKilobytes()]
                    : [$exigida ? 'required' : 'nullable', 'string'],
                default => [$exigida ? 'required' : 'nullable', 'string', 'max:500'],
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
            'receipt.required' => 'Anexe o comprovante do PIX para finalizar.',
            // Vários bancos compartilham o comprovante como PDF, não como imagem.
            'receipt.mimes' => 'O comprovante precisa ser uma foto (JPG, PNG ou HEIC) ou um PDF.',
            'receipt.max' => 'A imagem do comprovante passa de '.UploadLimit::label().'. Tire um print menor ou reduza a foto.',
        ];

        foreach ($this->campos as $campo) {
            $chave = 'respostas.'.$campo['key'];

            $mensagens[$chave.'.in'] = 'Escolha uma das opções de "'.$campo['question'].'".';
            $mensagens[$chave.'.*.in'] = 'Escolha uma das opções de "'.$campo['question'].'".';

            // Sem isto a mensagem sairia com o nome técnico do campo
            // ("O campo respostas.0-voce-vai-como é obrigatório").
            $mensagens[$chave.'.required'] = 'Responda "'.$campo['question'].'".';
            $mensagens[$chave.'.min'] = 'Marque ao menos uma opção em "'.$campo['question'].'".';
        }

        return $mensagens;
    }

    /**
     * O banco guarda a resposta indexada pelo texto da pergunta (é assim que o
     * painel e o dashboard exibem). O formulário trabalha com a chave estável:
     * pergunta com ponto quebrava o wire:model, e com aspas quebrava o HTML.
     */
    private function respostasParaRegistro(): array
    {
        $saida = [];

        foreach ($this->campos as $campo) {
            $valor = $this->respostas[$campo['key']] ?? ($campo['type'] === 'checkbox' ? [] : '');

            if ($campo['type'] === 'file') {
                /*
                 * O que fica gravado é o **caminho**, em disco privado — são
                 * documentos da pessoa (autorização de menor, ficha médica),
                 * da mesma natureza do comprovante bancário, e por isso saem
                 * só por URL assinada.
                 *
                 * Quando a pessoa volta para alterar outro dado sem reenviar o
                 * arquivo, o que está na propriedade já é o caminho antigo:
                 * guardamos como está, em vez de apagar o anexo.
                 */
                $saida[$campo['question']] = $valor instanceof UploadedFile
                    ? $valor->store('respostas', config('femopror.uploads.disk'))
                    : (string) $valor;

                continue;
            }

            $saida[$campo['question']] = $valor;
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

    @php
        $inscricao = $this->inscricao;

        // Com inscrição salva, a tela mostra o que está gravado — a não ser que
        // a pessoa tenha voltado para alterar (e as inscrições sigam abertas).
        $mostrarSalva = $inscricao && ! ($editando && $event->acceptsRegistrations());

        $valorExibido = $mostrarSalva ? $inscricao->valorCobrado() : $this->precoFinal;
        $adicionais = round($this->precoFinal - (float) $event->price, 2);
    @endphp

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
                <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ session('success_titulo', 'Inscrição enviada!') }}</h2>
                <p class="text-gray-600 max-w-md mb-6">{{ session('success') }}</p>
                <a href="{{ route('dashboard') }}" class="bg-green-900 text-white px-6 py-2.5 rounded-full font-bold shadow-md hover:bg-green-800 transition">
                    Ver minhas inscrições
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2">

                {{-- Coluna do evento. O QR saiu daqui: ficava ao lado do formulário e
                     convidava a pagar antes de escolher as modalidades. --}}
                <div class="p-6 sm:p-8 bg-green-950 text-white">
                    <span class="bg-white/10 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
                        {{ $event->event_date->format('d/m/Y H:i') }}
                    </span>
                    <h1 class="text-2xl font-bold mt-4 mb-2">{{ $event->title }}</h1>
                    <p class="text-green-200 text-sm leading-relaxed mb-6">{{ $event->description }}</p>

                    <div class="space-y-4 text-sm border-t border-white/10 pt-6">
                        @if($event->location)
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span>{{ $event->location }}</span>
                            </div>
                        @endif

                        <div aria-live="polite">
                            @if($valorExibido > 0)
                                <p class="text-xs uppercase tracking-wide text-green-300">
                                    {{ $mostrarSalva ? 'Valor da sua inscrição' : 'Valor' }}
                                </p>
                                <p class="text-2xl font-bold text-green-400 tabular-nums">
                                    R$ {{ number_format($valorExibido, 2, ',', '.') }}
                                </p>

                                @if(! $mostrarSalva && $adicionais > 0)
                                    <p class="mt-1 text-xs text-green-300 tabular-nums">
                                        R$ {{ number_format((float) $event->price, 2, ',', '.') }} da inscrição
                                        + R$ {{ number_format($adicionais, 2, ',', '.') }} das escolhas
                                    </p>
                                @endif
                            @else
                                <p class="text-lg font-bold text-green-400">Inscrição gratuita</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8">

                    {{-- ② PAGAMENTO — inscrição salva, falta o comprovante --}}
                    @if($mostrarSalva && $inscricao->isAwaitingReceipt())
                        <div>
                            {{-- Último passo: todas as páginas de preenchimento ficam com ✓. --}}
                            <x-etapas-inscricao
                                :paginas="$this->paginas"
                                :pagamento="true"
                                :no-pagamento="true" />

                            <h2 class="text-xl font-bold text-gray-900">Pagamento</h2>
                            <p class="mt-1 mb-5 text-sm text-gray-500">
                                Sua vaga está reservada. Agora é só pagar o PIX e anexar o comprovante.
                            </p>

                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 truncate">{{ $inscricao->name }}</p>
                                        <p class="text-gray-500 truncate">{{ $inscricao->church?->name }}</p>
                                    </div>

                                    @if($event->acceptsRegistrations())
                                        <button type="button" wire:click="alterarDados"
                                                class="flex-shrink-0 text-xs font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">
                                            Alterar dados
                                        </button>
                                    @endif
                                </div>

                                @foreach($inscricao->respostasParaExibicao() as $resposta)
                                    @if($resposta['rotulo'] || filled($resposta['texto']))
                                        <p class="mt-2 text-gray-700">
                                            <span class="text-gray-400">{{ $resposta['pergunta'] }}</span><br>
                                            @if($resposta['rotulo'])
                                                {{-- Caminho cru não serve para abrir nada. --}}
                                                @if($resposta['url'])
                                                    <a href="{{ $resposta['url'] }}" target="_blank" rel="noopener"
                                                       class="font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">
                                                        {{ $resposta['rotulo'] }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-400">Arquivo indisponível no momento</span>
                                                @endif
                                            @else
                                                {{ $resposta['texto'] }}
                                            @endif
                                        </p>
                                    @endif
                                @endforeach

                                <div class="mt-3 flex items-baseline justify-between border-t border-gray-200 pt-3">
                                    <span class="text-gray-500">Total</span>
                                    <span class="text-lg font-bold text-gray-900 tabular-nums">R$ {{ number_format($inscricao->valorCobrado(), 2, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="mt-5 rounded-xl border border-gray-200 p-5 text-center" x-data="{ copiado: false }">
                                <p class="mb-3 text-xs font-bold uppercase tracking-wider text-green-900">Pague com PIX</p>

                                <div class="inline-block rounded-xl border-2 border-gray-100 bg-white p-2">
                                    {{-- Serviço externo: se não responder, o copia-e-cola abaixo continua valendo. --}}
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($this->pixCopiaCola) }}"
                                         alt="QR Code do PIX no valor de R$ {{ number_format($inscricao->valorCobrado(), 2, ',', '.') }}"
                                         referrerpolicy="no-referrer"
                                         width="176" height="176"
                                         class="h-44 w-44"
                                         onerror="this.replaceWith(Object.assign(document.createElement('p'), { className: 'text-xs text-gray-500 p-4', textContent: 'Não foi possível carregar o QR Code. Use o código abaixo.' }))">
                                </div>

                                <p class="mt-3 mb-2 text-xs text-gray-500">Ou copie o código PIX:</p>

                                <div class="flex items-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                    <input type="text" value="{{ $this->pixCopiaCola }}" readonly x-ref="pix"
                                           aria-label="Código PIX copia e cola"
                                           class="w-full bg-transparent px-3 py-2 text-xs text-gray-600 outline-none">
                                    <button type="button"
                                            @click="navigator.clipboard.writeText($refs.pix.value).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })"
                                            class="whitespace-nowrap border-l border-gray-200 bg-green-100 px-3 py-2 text-xs font-bold text-green-900 transition-colors hover:bg-green-200">
                                        <span x-text="copiado ? 'COPIADO!' : 'COPIAR'">COPIAR</span>
                                    </button>
                                </div>

                                <p class="mt-3 text-[11px] leading-relaxed text-gray-400">
                                    Chave ({{ config('femopror.pix.display_label') }}):
                                    <span class="font-mono text-gray-600 select-all">{{ config('femopror.pix.display_key') }}</span><br>
                                    {{ config('femopror.pix.display_owner') }}
                                </p>
                            </div>

                            <form wire:submit.prevent="enviarComprovante" class="mt-5 space-y-4">
                                <x-campo-comprovante :tem-arquivo="(bool) $receipt" />

                                <button type="submit" wire:loading.attr="disabled" wire:target="enviarComprovante,receipt"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-green-900 py-3 font-bold text-white shadow-md transition hover:bg-green-800 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="enviarComprovante">Finalizar inscrição</span>
                                    <span wire:loading wire:target="enviarComprovante">Enviando comprovante...</span>
                                </button>
                            </form>

                            <p class="mt-4 text-center text-xs leading-relaxed text-gray-400">
                                Pode sair desta página: sua inscrição fica salva. Para enviar o comprovante
                                depois, é só voltar aqui ou abrir <a href="{{ route('dashboard') }}" class="font-semibold text-green-900 underline underline-offset-2">Minhas inscrições</a>.
                            </p>
                        </div>

                    {{-- INSCRIÇÃO CONCLUÍDA --}}
                    @elseif($mostrarSalva)
                        @php($status = $inscricao->statusKey())

                        <div class="flex h-full flex-col justify-center text-center">
                            <div @class([
                                'mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full',
                                'bg-green-100 text-green-700' => in_array($status, ['pago', 'gratuita']),
                                'bg-blue-50 text-blue-700' => $status === 'em_analise',
                                'bg-amber-50 text-amber-700' => $status === 'aguardando_pagamento',
                                'bg-red-50 text-red-700' => $status === 'cancelada',
                            ])>
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    @if($status === 'cancelada')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    @elseif(in_array($status, ['em_analise', 'aguardando_pagamento']))
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    @endif
                                </svg>
                            </div>

                            <h3 class="mb-2 text-2xl font-bold tracking-tight text-gray-900">
                                {{ $status === 'cancelada' ? 'Inscrição cancelada' : 'Você está inscrito!' }}
                            </h3>

                            <p class="mb-6 text-sm text-gray-600">
                                @switch($status)
                                    @case('pago') Pagamento confirmado pela tesouraria. Te esperamos lá! @break
                                    @case('gratuita') Sua inscrição está confirmada. Te esperamos lá! @break
                                    @case('em_analise') Recebemos seu comprovante. A diretoria está verificando se sua inscrição está totalmente válida, você receberá um e-mail quando o pagamento for confirmado. @break
                                    @case('aguardando_pagamento') Sua inscrição está salva. Assim que o PIX cair, a tesouraria confirma o pagamento. @break
                                    @case('cancelada') Esta inscrição foi cancelada. Em caso de dúvida, fale com a diretoria. @break
                                @endswitch
                            </p>

                            {{-- Quem volta ao evento já inscrito precisa ver, de cara, em que pé
                                 está — não só "você está inscrito". O índice único
                                 (event_id, user_id) impede a segunda inscrição no banco; esta
                                 tela é o que explica para a pessoa por que não dá. --}}
                            @php($cor = $inscricao->statusColor())
                            <div class="mx-auto mb-6 flex flex-col items-center gap-2">
                                <span @class([
                                    'inline-block rounded-lg px-3.5 py-1.5 text-sm font-semibold',
                                    'bg-green-50 text-green-800' => $cor === 'success',
                                    'bg-blue-50 text-blue-800' => $cor === 'info',
                                    'bg-amber-50 text-amber-800' => $cor === 'warning',
                                    'bg-red-50 text-red-700' => $cor === 'danger',
                                ])>
                                    {{ $inscricao->statusLabel() }}
                                </span>

                                <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">
                                    Veja mais detalhes em Minhas inscrições →
                                </a>
                            </div>

                            @if($status === 'aguardando_pagamento' && $this->pixCopiaCola !== '')
                                <div class="mb-6 rounded-xl border border-gray-200 p-4" x-data="{ copiado: false }">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wider text-green-900">
                                        PIX de R$ {{ number_format($inscricao->valorCobrado(), 2, ',', '.') }}
                                    </p>
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($this->pixCopiaCola) }}"
                                         alt="QR Code do PIX" referrerpolicy="no-referrer" width="160" height="160" class="mx-auto h-40 w-40">
                                    <div class="mt-3 flex items-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                        <input type="text" value="{{ $this->pixCopiaCola }}" readonly x-ref="pix" aria-label="Código PIX copia e cola" class="w-full bg-transparent px-3 py-2 text-xs text-gray-600 outline-none">
                                        <button type="button" @click="navigator.clipboard.writeText($refs.pix.value).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })"
                                                class="whitespace-nowrap border-l border-gray-200 bg-green-100 px-3 py-2 text-xs font-bold text-green-900 hover:bg-green-200">
                                            <span x-text="copiado ? 'COPIADO!' : 'COPIAR'">COPIAR</span>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- O comprovante mora em disco privado: sem este bloco a pessoa
                                 não tinha como nem ver qual arquivo tinha mandado, quanto mais
                                 corrigir um print errado. --}}
                            @if(filled($inscricao->receipt_path))
                                @php($urlComprovante = $inscricao->receiptUrl())

                                <div class="mb-6 rounded-xl border border-gray-200 p-4 text-left">
                                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Comprovante enviado</p>

                                    @if(session('comprovante_trocado'))
                                        <p class="mb-3 rounded-lg bg-green-50 px-3 py-2 text-xs font-medium text-green-800">
                                            {{ session('comprovante_trocado') }}
                                        </p>
                                    @endif

                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                @if($inscricao->receiptIsPdf())
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                @endif
                                            </svg>
                                        </div>

                                        <div class="min-w-0 flex-grow text-sm">
                                            <p class="font-medium text-gray-900">{{ $inscricao->receiptIsPdf() ? 'Documento PDF' : 'Imagem' }}</p>
                                            <p class="text-xs text-gray-500">Enviado em {{ $inscricao->updated_at->format('d/m/Y \à\s H:i') }}</p>
                                        </div>

                                        @if($urlComprovante)
                                            {{-- Link assinado, válido por 30 minutos. --}}
                                            <a href="{{ $urlComprovante }}" target="_blank" rel="noopener noreferrer"
                                               class="flex-shrink-0 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                                                Ver
                                            </a>
                                        @endif
                                    </div>

                                    @if($inscricao->canReplaceReceipt())
                                        @if(! $substituindoComprovante)
                                            <button type="button" wire:click="trocarComprovante"
                                                    class="mt-3 text-xs font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">
                                                Mandei o arquivo errado, trocar comprovante
                                            </button>
                                        @else
                                            <form wire:submit.prevent="enviarComprovante" class="mt-4 space-y-3">
                                                <x-campo-comprovante :tem-arquivo="(bool) $receipt" titulo="Escolher o arquivo certo" />

                                                <div class="flex gap-2">
                                                    <button type="submit" wire:loading.attr="disabled" wire:target="enviarComprovante,receipt"
                                                            class="flex-grow rounded-xl bg-green-900 py-2.5 text-sm font-bold text-white transition hover:bg-green-800 disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="enviarComprovante">Substituir comprovante</span>
                                                        <span wire:loading wire:target="enviarComprovante">Enviando...</span>
                                                    </button>

                                                    <button type="button" wire:click="cancelarTrocaComprovante"
                                                            class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </form>
                                        @endif
                                    @elseif($inscricao->isPaid())
                                        <p class="mt-3 text-xs leading-relaxed text-gray-400">
                                            O pagamento já foi confirmado pela tesouraria, então o comprovante
                                            não pode mais ser trocado. Se houver algo errado, fale com a diretoria.
                                        </p>
                                    @endif
                                </div>
                            @endif

                            <a href="{{ route('dashboard') }}" class="mx-auto inline-flex items-center justify-center gap-2 rounded-full bg-green-900 px-6 py-3 font-bold text-white shadow-md transition-colors hover:bg-green-800">
                                Ver minhas inscrições
                            </a>
                        </div>

                    {{-- Evento encerrado ou com inscrição ainda não aberta --}}
                    @elseif(! $event->acceptsRegistrations())
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

                    {{--
                        A tela "Escolha sua categoria" (Sou Visitante / Sou
                        Delegado) saiu daqui.

                        Ela bifurcava o congresso antes do formulário e mandava
                        quem clicasse em "Sou Delegado" para o `/ump` — que é
                        painel de presidente de UMP. O jovem delegado comum
                        tomava 403 na cara, e a inscrição dele simplesmente não
                        acontecia.

                        Agora todo mundo preenche o mesmo formulário e **declara
                        a categoria** numa pergunta (ver
                        `App\Support\PerguntasPadraoDoCongresso`), que é como a
                        federação já fazia no formulário de papel. O `/ump`
                        continua existindo para o que é da UMP: comprovante,
                        relatórios, delegação oficial e diretoria.
                    --}}

                    {{-- ① PREENCHIMENTO (uma ou várias páginas) --}}
                    @else
                        @auth
                            @php($paginaAtual = $this->paginas[$pagina] ?? $this->paginas[0])
                            {{--
                                "Alterar dados" é edição do que já foi salvo: mostra tudo
                                numa tela só. Paginar aqui esconderia pergunta de outra
                                página de quem só quer corrigir o telefone — e, pior,
                                `salvarDados()` regrava `custom_answers` inteiro, então o
                                que não estivesse em tela se perderia.
                            --}}
                            @php($ultimaPagina = $editando || $pagina >= count($this->paginas) - 1)

                            {{--
                                O stepper só aparece quando há mais de um passo.
                                Num evento gratuito de página única ele mostraria
                                um passo só, que não informa nada.
                            --}}
                            @if(! $editando && (count($this->paginas) > 1 || $this->precoFinal > 0))
                                <x-etapas-inscricao
                                    :paginas="$this->paginas"
                                    :pagina="$pagina"
                                    :pagamento="$this->precoFinal > 0" />
                            @endif

                            <div class="flex items-center justify-between mb-5">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">
                                        {{ $editando ? 'Alterar dados' : $paginaAtual['title'] }}
                                    </h2>
                                    @if(! $editando && $paginaAtual['description'])
                                        <p class="mt-1 text-sm text-gray-500">{{ $paginaAtual['description'] }}</p>
                                    @endif
                                </div>
                            </div>

                            @unless($editando || $pagina > 0)
                                <p class="mb-5 flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs leading-relaxed text-gray-500">
                                    <svg class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 16v-4m0-3.5h.01" />
                                    </svg>
                                    <span>
                                        Preenchemos com os dados da sua conta. Para mudar de vez,
                                        edite o <a href="{{ route('profile.edit') }}" class="font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">seu perfil</a>.
                                    </span>
                                </p>
                            @endunless

                            {{--
                                Na última página o envio grava; nas anteriores,
                                valida só o que está em tela e avança. Validar o
                                formulário inteiro a cada passo mostraria erro
                                de campo que a pessoa ainda nem viu.
                            --}}
                            <form wire:submit.prevent="{{ $ultimaPagina ? 'salvarDados' : 'proximaPagina' }}" class="space-y-5">
                                @if($pagina === 0 || $editando)
                                <div>
                                    <label for="campo-nome" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Nome Completo</label>
                                    {{-- `value` explícito: evita o campo aparecer vazio antes de o Livewire aplicar o estado. --}}
                                    <input id="campo-nome" type="text" wire:model="name" value="{{ $name }}" autocomplete="name"
                                           @error('name') aria-invalid="true" @enderror
                                           class="w-full border rounded-xl px-4 py-2.5 text-sm focus:ring-1 transition outline-none @error('name') border-red-400 focus:border-red-500 focus:ring-red-500 @else border-gray-200 focus:border-green-900 focus:ring-green-900 @enderror">
                                    @error('name') <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="campo-email" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">E-mail</label>
                                    <input id="campo-email" type="email" wire:model="email" value="{{ $email }}" autocomplete="email"
                                           class="w-full border rounded-xl px-4 py-2.5 text-sm focus:ring-1 transition outline-none @error('email') border-red-400 focus:border-red-500 focus:ring-red-500 @else border-gray-200 focus:border-green-900 focus:ring-green-900 @enderror">
                                    @error('email') <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="campo-telefone" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">WhatsApp / Telefone</label>
                                    <input id="campo-telefone" type="tel" wire:model="phone" value="{{ $phone }}"
                                           inputmode="numeric" maxlength="16" autocomplete="tel" placeholder="(84) 99999-9999"
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
                                @endif

                                @php($camposDaPagina = $editando ? $this->campos : $this->camposDaPagina($pagina))

                                @if(count($camposDaPagina) > 0)
                                    <div @class(['pt-4 mt-4 border-t border-gray-100' => $pagina === 0])>
                                        {{-- O título "Informações Adicionais" só faz sentido quando
                                             as perguntas dividem a tela com os dados fixos. Numa
                                             página própria, o título dela já é o cabeçalho. --}}
                                        @if($pagina === 0)
                                            <h3 class="text-sm font-bold text-green-900 mb-4 uppercase tracking-wider">Informações Adicionais</h3>
                                        @endif

                                        @foreach($camposDaPagina as $campo)
                                            <fieldset class="mb-5" wire:key="campo-{{ $campo['key'] }}">
                                                <legend class="block text-xs font-bold text-gray-700 mb-2">
                                                    {{ $campo['question'] }}
                                                    @if($campo['required'] ?? false)
                                                        <span class="text-red-600" aria-hidden="true">*</span>
                                                        <span class="sr-only">(obrigatória)</span>
                                                    @endif
                                                </legend>

                                                @if($campo['image_path'])
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($campo['image_path']) }}"
                                                         alt="Ilustração de: {{ $campo['question'] }}"
                                                         loading="lazy"
                                                         class="mb-3 max-h-48 w-auto rounded-xl border border-gray-200">
                                                @endif

                                                @if($campo['type'] === 'checkbox' || $campo['type'] === 'radio')
                                                    {{--
                                                        Checkbox e rádio desenham igual, e mudam só em
                                                        `type` e no nome: rádio precisa de `name` comum
                                                        para o navegador agrupar as opções e deixar
                                                        escolher uma só.
                                                    --}}
                                                    @php($ehRadio = $campo['type'] === 'radio')

                                                    <div class="space-y-2">
                                                        @foreach($campo['options'] as $opcao)
                                                            <label class="flex items-center gap-3 cursor-pointer p-3 border border-gray-200 rounded-xl hover:bg-green-50 hover:border-green-300 transition-all bg-white shadow-sm">
                                                                <input type="{{ $ehRadio ? 'radio' : 'checkbox' }}"
                                                                    @if($ehRadio) name="resposta-{{ $campo['key'] }}" @endif
                                                                    wire:model.live="respostas.{{ $campo['key'] }}"
                                                                    value="{{ $opcao['label'] }}"
                                                                    class="w-5 h-5 {{ $ehRadio ? '' : 'rounded' }} border-gray-300 text-green-900 focus:ring-green-900 transition">
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

                                                @elseif($campo['type'] === 'textarea')
                                                    <textarea wire:model.blur="respostas.{{ $campo['key'] }}" rows="4" maxlength="2000"
                                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none"></textarea>

                                                @elseif($campo['type'] === 'file')
                                                    {{--
                                                        Campo de arquivo passa pelo mesmo componente do
                                                        comprovante, e não por um `<input>` solto: é ele
                                                        que escuta os eventos de upload do Livewire e
                                                        tem o vigia de travamento. Sem isso, toda falha
                                                        de envio vira "escolhi o arquivo e não aconteceu
                                                        nada".
                                                    --}}
                                                    <x-campo-comprovante
                                                        campo="respostas.{{ $campo['key'] }}"
                                                        titulo="Anexar arquivo"
                                                        :tem-arquivo="filled(data_get($respostas, $campo['key']))" />
                                                @else
                                                    <input type="text" wire:model.blur="respostas.{{ $campo['key'] }}" maxlength="500" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-green-900 focus:ring-1 focus:ring-green-900 transition outline-none">
                                                @endif

                                                @if($campo['help'])
                                                    <p class="mt-1.5 text-xs leading-relaxed text-gray-500">{{ $campo['help'] }}</p>
                                                @endif

                                                @error('respostas.'.$campo['key']) <span class="text-red-600 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                            </fieldset>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Total corrente antes de seguir: a pessoa vê o que vai pagar
                                     antes de o PIX existir. --}}
                                @if($this->precoFinal > 0)
                                    <div class="flex items-baseline justify-between rounded-xl bg-gray-50 px-4 py-3" aria-live="polite">
                                        <span class="text-sm text-gray-500">Total</span>
                                        <span class="text-lg font-bold text-gray-900 tabular-nums">R$ {{ number_format($this->precoFinal, 2, ',', '.') }}</span>
                                    </div>
                                @endif

                                @php($acao = $ultimaPagina ? 'salvarDados' : 'proximaPagina')

                                <div class="flex items-center gap-3">
                                    {{-- O "Voltar" é botão comum e fica FORA do submit:
                                         dentro do form, um `<button>` sem type vira submit
                                         e avançaria em vez de voltar. --}}
                                    @if($pagina > 0 && ! $editando)
                                        <button type="button" wire:click="paginaAnterior"
                                                class="shrink-0 rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                                            ← Voltar
                                        </button>
                                    @endif

                                    <button type="submit" wire:loading.attr="disabled" wire:target="{{ $acao }}"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-green-900 py-3 font-bold text-white shadow-md transition hover:bg-green-800 disabled:opacity-50">
                                        <span wire:loading.remove wire:target="{{ $acao }}">
                                            @if(! $ultimaPagina)
                                                Continuar →
                                            @else
                                                {{ $this->precoFinal > 0 ? 'Ir para pagamento →' : 'Confirmar inscrição' }}
                                            @endif
                                        </span>
                                        <span wire:loading wire:target="{{ $acao }}">{{ $ultimaPagina ? 'Salvando...' : 'Carregando...' }}</span>
                                    </button>
                                </div>

                                @if($editando)
                                    <button type="button" wire:click="cancelarAlteracao" class="w-full text-center text-sm text-gray-500 hover:text-gray-900">
                                        Cancelar e voltar ao pagamento
                                    </button>
                                @endif
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

                                {{-- Os dois caminhos levam o `redirect` de volta para este evento. --}}
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
