{{-- Classe pelo nome completo de propósito: `@php use ... @endphp` numa view
     Blade acaba fora do topo do arquivo compilado e quebra com
     "unexpected token use". --}}
<x-guest-layout
    title="Entrar"
    :subtitle="\App\Support\IntendedUrl::hasDestination()
        ? 'Depois de entrar você volta direto para a inscrição, sem perder o que já escolheu.'
        : 'Acesse sua conta para se inscrever nos eventos e acompanhar o status das suas inscrições.'">

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email')" placeholder="voce@exemplo.com" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <div class="flex items-baseline justify-between gap-2">
                <x-input-label for="password" value="Senha" class="mb-1.5" />

                @if (Route::has('password.request'))
                    <a class="mb-1.5 text-xs font-medium text-gray-500 transition-colors hover:text-green-900" href="{{ route('password.request') }}">
                        Esqueceu a senha?
                    </a>
                @endif
            </div>

            <x-text-input id="password" name="password" type="password" placeholder="••••••••" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label for="remember_me" class="flex cursor-pointer items-center gap-2.5 select-none">
            <input id="remember_me" name="remember" type="checkbox"
                   class="h-4 w-4 rounded border-gray-300 text-green-900 transition focus:ring-1 focus:ring-green-900 focus:ring-offset-0">
            <span class="text-sm text-gray-600">Continuar conectado neste dispositivo</span>
        </label>

        <x-primary-button class="w-full">Entrar</x-primary-button>
    </form>

    <div class="mt-6 border-t border-gray-100 pt-6 text-center text-sm text-gray-500">
        Ainda não tem conta?
        {{-- O link leva o destino junto: alternar entre as duas telas não pode
             fazer a pessoa perder o evento de onde ela veio. --}}
        <a href="{{ \App\Support\IntendedUrl::linkTo('register') }}" class="font-semibold text-green-900 transition-colors hover:text-green-700">
            Criar conta
        </a>
    </div>
</x-guest-layout>
