<section class="grid grid-cols-1 gap-8 lg:grid-cols-3">

    <header class="lg:col-span-1">
        <h2 class="text-base font-semibold text-gray-900">Informações do perfil</h2>

        <p class="mt-1.5 text-sm leading-relaxed text-gray-500">
            Aqui você compartilha alguns dados pessoais.
        </p>

        {{--
            A igreja e o WhatsApp eram pedidos de novo a cada inscrição. Guardados
            aqui, o formulário do evento já vem preenchido.
        --}}
        <p class="mt-3 text-sm leading-relaxed text-gray-500">
            A <strong class="font-medium text-gray-700">igreja</strong> e o
            <strong class="font-medium text-gray-700">WhatsApp</strong> são usados para
            preencher automaticamente o formulário de inscrição dos eventos.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5 lg:col-span-2">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Nome completo" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="mt-2 text-sm text-gray-600">
                    Seu e-mail não está verificado.
                    <button form="send-verification" class="font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">
                        Reenviar o e-mail de verificação.
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-green-700">
                        Um novo link de verificação foi enviado.
                    </p>
                @endif
            @endif
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="church_id" value="Sua igreja local" :optional="true" />
                <x-select-input id="church_id" name="church_id">
                    <option value="">Selecione sua igreja...</option>
                    @foreach($churches as $church)
                        <option value="{{ $church->id }}" @selected(old('church_id', $user->church_id) == $church->id)>
                            {{ $church->name }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('church_id')" />
            </div>

            <div>
                <x-input-label for="phone" value="WhatsApp" :optional="true" />
                <x-text-input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="16"
                              :value="old('phone', $user->phone)" placeholder="(84) 99999-9999" autocomplete="tel" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>
        </div>

        @if($user->isChurchPresident())
            <div class="flex items-start gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-3 text-sm text-gray-600">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-800" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                </svg>
                <span>
                    Você é o presidente da UMP de <strong class="font-medium text-gray-800">{{ $user->church?->name }}</strong>.
                    <a href="{{ url('/ump') }}" class="font-semibold text-green-900 underline underline-offset-2 hover:text-green-700">Acessar a área da UMP</a>.
                </span>
            </div>
        @endif

        <div class="flex items-center gap-4 pt-1">
            <x-primary-button>Salvar alterações</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="flex items-center gap-1.5 text-sm font-medium text-green-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Salvo
                </p>
            @endif
        </div>
    </form>
</section>
