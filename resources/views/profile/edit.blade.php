<x-app-layout>
    <x-slot name="title">Meu perfil</x-slot>

    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-gray-900">Meu perfil</h2>
            <p class="mt-1 text-sm text-gray-500">Seus dados de acesso e as informações que preenchem as inscrições.</p>
        </div>
    </x-slot>

    {{--
        Layout de página de ajustes: título e explicação à esquerda, formulário à
        direita, separados por um fio. Cada bloco é um assunto — não uma pilha de
        cartões iguais com sombra.
    --}}
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white">

            <div class="p-6 sm:p-8">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="p-6 sm:p-8">
                @include('profile.partials.update-password-form')
            </div>

            <div class="p-6 sm:p-8">
                @include('profile.partials.delete-user-form')
            </div>

        </div>
    </div>
</x-app-layout>
