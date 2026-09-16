<x-guest-layout title="Confirme sua senha" subtitle="Esta é uma área protegida. Confirme a senha para continuar.">

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" name="password" type="password" placeholder="••••••••" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <x-primary-button class="w-full">Confirmar</x-primary-button>
    </form>
</x-guest-layout>
