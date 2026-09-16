<x-guest-layout title="Recuperar senha" subtitle="Informe o e-mail da sua conta e enviamos um link para você escolher uma senha nova.">

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email')" placeholder="voce@exemplo.com" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <x-primary-button class="w-full">Enviar link de recuperação</x-primary-button>
    </form>

    <div class="mt-6 border-t border-gray-100 pt-6 text-center text-sm text-gray-500">
        Lembrou a senha?
        <a href="{{ route('login') }}" class="font-semibold text-green-900 transition-colors hover:text-green-700">
            Voltar para o login
        </a>
    </div>
</x-guest-layout>
