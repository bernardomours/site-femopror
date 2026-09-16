@props(['disabled' => false, 'name' => null])

@php
    // O campo se pinta de vermelho sozinho quando tem erro, em vez de cada
    // formulário repetir a lógica.
    $temErro = $name && $errors->has($name);

    $classes = 'block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 transition-colors duration-150 outline-none disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 '
        .($temErro
            ? 'border-red-300 focus:border-red-500 focus:ring-1 focus:ring-red-500'
            : 'border-gray-200 hover:border-gray-300 focus:border-green-900 focus:ring-1 focus:ring-green-900');
@endphp

<input
    @disabled($disabled)
    @if($name) name="{{ $name }}" @endif
    @if($temErro) aria-invalid="true" @endif
    {{ $attributes->merge(['class' => $classes]) }}>
