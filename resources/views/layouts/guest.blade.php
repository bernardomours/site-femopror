@props(['title' => null, 'subtitle' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        <title>{{ $title ? $title.' · FEMOPROR' : 'FEMOPROR' }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Única fonte de Alpine no app — ver `resources/js/app.js`. --}}
        @livewireScripts
    </head>

    {{--
        A barra verde de 8px no topo e a sombra pesada saíram. A página se
        segura em espaço em branco, um fio de 1px e um bloco de cor só onde
        tem significado (o botão). Sem gradiente, sem cartão flutuando.
    --}}
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-12">

            <div class="mb-8">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 transition-colors hover:text-green-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Voltar para o site
                </a>
            </div>

            <div class="mb-8">
                <a href="{{ route('home') }}" class="inline-block">
                    <img src="{{ asset('images/topo.png') }}" alt="FEMOPROR" width="400" height="133" class="h-10 w-auto">
                </a>

                @if($title)
                    <h1 class="mt-6 text-2xl font-bold tracking-tight text-gray-900">{{ $title }}</h1>
                @endif

                @if($subtitle)
                    <p class="mt-1.5 text-sm leading-relaxed text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-8 text-center text-xs text-gray-400">
                Federação de Mocidades do Presbitério Oeste Rio-Grandense
            </p>
        </div>
    </body>
</html>
