<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>FEMOPROR</title>

        <!-- Fontes -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts do Tailwind -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Estilos do Livewire -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased text-gray-900 bg-gray-50">
        
        <!-- Aqui é onde a sua home-page vai ser injetada! -->
        {{ $slot }}

        <!-- Scripts do Livewire -->
        @livewireScripts
    </body>
</html>