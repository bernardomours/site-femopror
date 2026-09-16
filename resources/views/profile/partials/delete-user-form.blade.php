<section class="grid grid-cols-1 gap-8 lg:grid-cols-3">

    <header class="lg:col-span-1">
        <h2 class="text-base font-semibold text-gray-900">Apagar conta</h2>

        <p class="mt-1.5 text-sm leading-relaxed text-gray-500">
            Suas inscrições e o histórico de participação vão junto, e não há como desfazer.
        </p>
    </header>

    <div class="lg:col-span-2">
        <x-danger-button
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >Apagar minha conta</x-danger-button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <h2 class="text-lg font-semibold text-gray-900">
                Apagar sua conta?
            </h2>

            <p class="mt-2 text-sm leading-relaxed text-gray-600">
                Tudo que está ligado a ela é removido em definitivo, inclusive as inscrições
                já enviadas. Confirme com a sua senha para continuar.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Senha" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Sua senha"
                    class="sm:max-w-sm"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button>
                    Apagar conta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
