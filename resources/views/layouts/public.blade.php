<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#14532d">

        {{--
            O <title> era fixo "FEMOPROR" em toda página e não havia nenhuma
            meta de compartilhamento. Um link de evento colado no WhatsApp ou
            no story aparecia sem título, sem descrição e sem imagem — o que
            custa inscrição num site cujo tráfego vem de rede social.

            Cada página empilha o que é seu com @push('head'); o que está aqui
            é só o padrão de quem não empilhou nada.
        --}}
        @php($headDaPagina = trim($__env->yieldPushContent('head')))

        {!! $headDaPagina !!}

        @if ($headDaPagina === '')
            <title>FEMOPROR · Federação de Mocidades do Presbitério Oeste Rio-Grandense</title>
            <meta name="description" content="Portal da FEMOPROR: próximos eventos, inscrições, diretoria das UMPs locais e materiais oficiais para download.">
            <meta property="og:title" content="FEMOPROR">
            <meta property="og:description" content="Federação de Mocidades do Presbitério Oeste Rio-Grandense.">
            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ url()->current() }}">
            <meta property="og:image" content="{{ asset('images/tema-anual.png') }}">
        @endif

        <meta property="og:site_name" content="FEMOPROR">
        <meta property="og:locale" content="pt_BR">
        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="font-sans antialiased text-gray-900 bg-gray-50">

        {{-- Pular direto para o conteúdo: quem navega por teclado não precisa
             passar por todos os links do menu em cada página. --}}
        <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:z-[100] focus:top-4 focus:left-4 focus:bg-green-900 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:font-bold">
            Pular para o conteúdo
        </a>

        {{ $slot }}

        @livewireScripts
    </body>
</html>
