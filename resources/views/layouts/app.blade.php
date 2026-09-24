<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        <title>{{ $title ?? 'Portal' }} · FEMOPROR</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{--
            O Alpine desta página vem daqui. Esta tela não tem componente
            Livewire, mas o menu e os modais usam `x-data` — sem o script do
            Livewire não haveria Alpine nenhum. É também o que garante uma
            instância só: ver o comentário em `resources/js/app.js`.
        --}}
        @livewireScripts
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-50">
            @include('layouts.navigation')

            @isset($header)
                <header class="border-b border-gray-200 bg-white">
                    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
