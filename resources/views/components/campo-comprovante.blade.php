@props([
    'temArquivo' => false,
    'titulo' => 'Anexar comprovante do PIX',
])

@php
    $maxBytes = \App\Support\UploadLimit::maxBytes();
    $maxLabel = \App\Support\UploadLimit::label();
@endphp

{{--
    Campo de comprovante. Um componente só, usado no envio e na troca: o conserto
    do upload precisa valer nos dois lugares, e antes eram duas cópias.

    Os três problemas que este bloco resolve, todos com o mesmo sintoma para quem
    usa ("escolhi o arquivo e não aconteceu nada"):

    1. Arquivo maior que o `upload_max_filesize` do servidor é descartado pelo
       PHP antes do Laravel — nenhuma validação chega a rodar.
    2. Falha no upload do Livewire (rede, CORS, 413, sessão expirada) não
       aparecia em lugar nenhum: o evento `livewire-upload-error` existia e
       ninguém escutava.
    3. Foto de iPhone vem em HEIC. Sem HEIC no `accept`, o seletor de arquivos
       escondia a foto ou mandava um formato que a validação recusava depois.
--}}
<div x-data="{
        maxBytes: {{ $maxBytes }},
        maxLabel: @js($maxLabel),
        erro: '',
        aviso: '',
        enviando: false,
        progresso: 0,
        vigia: null,

        /*
         * Vigia de travamento.
         *
         * Nem toda falha de upload chega como `livewire-upload-error`. Quando o
         * endpoint responde HTML em vez de JSON — arquivo maior que o
         * `post_max_size` do PHP, sessão expirada (419), redirect de login,
         * CORS, 500 — o Livewire quebra no `JSON.parse` dentro da própria
         * promise: nenhum callback de erro roda e nenhum evento é disparado.
         * O upload simplesmente para, e a tela fica calada. É exatamente o
         * relato: seleciono o arquivo e não acontece nada.
         *
         * Nada de aspas duplas aqui dentro, nem em comentário: este bloco todo
         * é o valor do atributo x-data, que é delimitado por aspas duplas. A
         * primeira que aparecer encerra o atributo, e todo o resto do código
         * vaza como texto na tela. Use aspas simples ou crase.
         *
         * Então não confiamos só no evento de erro: se o progresso parar de
         * andar e nada mais chegar, assumimos travado. A conta é sobre a última
         * notícia recebida, não sobre o tempo total — num 4G ruim um envio
         * demorado é normal, e não pode virar mensagem de erro.
         */
        vigiar() {
            clearTimeout(this.vigia)
            this.vigia = setTimeout(() => {
                if (! this.enviando) return

                this.enviando = false
                this.erro = 'O envio travou no meio do caminho. Tente de novo — se repetir, o arquivo pode estar grande demais para o servidor: mande um print da tela do banco ou o PDF do comprovante. Se ainda assim não for, recarregue a página e entre de novo.'
            }, 20000)
        },

        pararVigia() {
            clearTimeout(this.vigia)
            this.vigia = null
        },

        conferir(evento) {
            const arquivo = evento.target.files[0]
            this.erro = ''
            this.aviso = ''

            if (! arquivo) return

            if (arquivo.size > this.maxBytes) {
                const mb = (arquivo.size / 1048576).toFixed(1)
                this.erro = `Este arquivo tem ${mb} MB, e o limite é ${this.maxLabel}. Tire um print da tela do banco (costuma ser bem menor que a foto) ou envie o comprovante em PDF.`
                // Limpa para o Livewire não tentar enviar algo que o servidor recusa.
                evento.target.value = ''

                return
            }

            /*
             * HEIC é o formato da câmera do iPhone. O arquivo é aceito, mas
             * Chrome e Edge no Windows não exibem HEIC, e este servidor não tem
             * como converter. Quem confere o comprovante pode não conseguir
             * abrir — então avisamos aqui, sem bloquear.
             */
            if (/\.(heic|heif)$/i.test(arquivo.name)) {
                this.aviso = 'Essa é uma foto no formato do iPhone (HEIC). Vai ser enviada, mas quem confere pode ter dificuldade para abrir. Se der, prefira um print da tela do banco ou o PDF do comprovante.'
            }
        },
     }"
     x-on:livewire-upload-start="erro = ''; enviando = true; progresso = 0; vigiar()"
     x-on:livewire-upload-finish="pararVigia(); enviando = false; progresso = 100"
     x-on:livewire-upload-cancel="pararVigia(); enviando = false"
     x-on:livewire-upload-error="pararVigia(); enviando = false; erro = 'Não conseguimos enviar o arquivo. Confira sua conexão e tente de novo — se continuar, tente pelo Wi-Fi ou envie um arquivo menor.'"
     x-on:livewire-upload-progress="progresso = $event.detail.progress; vigiar()"
     class="relative rounded-xl border-2 border-dashed bg-gray-50 p-4 text-center"
     :class="erro ? 'border-red-300' : '@error('receipt') border-red-300 @else border-gray-200 @enderror'">

    <label class="block cursor-pointer">
        <svg class="mx-auto mb-2 h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
        </svg>

        <span class="block text-xs font-semibold text-green-900">{{ $titulo }}</span>
        <span class="text-[10px] text-gray-400">Foto ou PDF, até {{ $maxLabel }}</span>

        <input type="file"
               wire:model="receipt"
               @change="conferir($event)"
               class="sr-only"
               accept="image/png,image/jpeg,image/heic,image/heif,application/pdf">
    </label>

    {{-- Barra de progresso: num 4G lento, upload sem retorno visual parece travado. --}}
    <div x-show="enviando" x-cloak class="mt-3">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
            <div class="h-full bg-green-700 transition-all duration-150" :style="`width: ${progresso}%`"></div>
        </div>
        <p class="mt-1.5 text-xs font-semibold text-gray-500">Enviando arquivo... <span x-text="progresso + '%'"></span></p>
    </div>

    <div x-show="erro" x-cloak class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-left text-xs font-medium leading-relaxed text-red-700" x-text="erro"></div>

    <div x-show="aviso" x-cloak class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-left text-xs font-medium leading-relaxed text-amber-800" x-text="aviso"></div>

    @if ($temArquivo)
        <div x-show="! erro && ! enviando" class="mt-2 flex items-center justify-center gap-1 text-xs font-bold text-green-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Arquivo pronto para enviar
        </div>
    @endif

    @error('receipt')
        <span class="mt-1 block text-left text-xs font-medium text-red-600">{{ $message }}</span>
    @enderror
</div>
