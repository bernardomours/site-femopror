@props(['active' => false])

@php
    // Era um border-bottom indigo, cor que não é do projeto. Aqui o estado ativo
    // é um bloco discreto, sem sublinhado nem deslocamento do texto.
    $classes = 'inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-150 '
        .($active
            ? 'bg-green-50 text-green-900'
            : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    {{ $slot }}
</a>
