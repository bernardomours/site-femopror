<x-guest-layout title="Definir nova senha" subtitle="Escolha uma senha que você não use em outros sites.">

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Nova senha" />
            <x-text-input id="password" name="password" type="password" placeholder="Pelo menos 8 caracteres" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirme a nova senha" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" placeholder="Repita a senha" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button class="w-full">Salvar nova senha</x-primary-button>
    </form>
</x-guest-layout>
