<section class="grid grid-cols-1 gap-8 lg:grid-cols-3">

    <header class="lg:col-span-1">
        <h2 class="text-base font-semibold text-gray-900">Senha</h2>

        <p class="mt-1.5 text-sm leading-relaxed text-gray-500">
            Use uma senha longa e que você não repita em outros sites.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-5 lg:col-span-2">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Senha atual" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" placeholder="••••••••" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="update_password_password" value="Nova senha" />
                <x-text-input id="update_password_password" name="password" type="password" placeholder="Pelo menos 8 caracteres" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" value="Confirme a nova senha" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" placeholder="Repita a senha" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />
            </div>
        </div>

        <div class="flex items-center gap-4 pt-1">
            <x-primary-button>Alterar senha</x-primary-button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="flex items-center gap-1.5 text-sm font-medium text-green-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Senha alterada
                </p>
            @endif
        </div>
    </form>
</section>
