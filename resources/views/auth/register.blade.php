{{-- Ver a nota em login.blade.php sobre por que a classe vai pelo nome completo. --}}
<x-guest-layout
    title="Criar conta"
    :subtitle="\App\Support\IntendedUrl::hasDestination()
        ? 'Leva um minuto. Assim que terminar, você volta para a página do evento para concluir a inscrição.'
        : 'Leva um minuto. Depois é só completar o perfil com a sua igreja e o WhatsApp, e as inscrições já vêm preenchidas.'">

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="name" value="Nome completo" />
            <x-text-input id="name" name="name" type="text" :value="old('name')" placeholder="Como você quer ser chamado" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email')" placeholder="voce@exemplo.com" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" name="password" type="password" placeholder="Pelo menos 8 caracteres" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirme a senha" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" placeholder="Repita a senha" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button class="w-full">Criar minha conta</x-primary-button>
    </form>

    <div class="mt-6 border-t border-gray-100 pt-6 text-center text-sm text-gray-500">
        Já tem conta?
        <a href="{{ \App\Support\IntendedUrl::linkTo('login') }}" class="font-semibold text-green-900 transition-colors hover:text-green-700">
            Entrar
        </a>
    </div>
</x-guest-layout>
