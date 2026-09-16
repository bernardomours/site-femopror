<x-guest-layout title="Verifique seu e-mail" subtitle="Enviamos um link de confirmação para o endereço que você cadastrou. Clique nele para liberar o acesso.">

    @if (session('status') == 'verification-link-sent')
        <x-auth-session-status class="mb-6" status="Enviamos um novo link de verificação para o seu e-mail." />
    @endif

    <div class="space-y-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button class="w-full">Reenviar e-mail de verificação</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-center text-sm text-gray-500 transition-colors hover:text-gray-900">
                Sair da conta
            </button>
        </form>
    </div>
</x-guest-layout>
