@props(['active' => false])

@php
    $classes = 'block w-full rounded-lg px-3 py-2.5 text-start text-sm font-medium transition-colors duration-150 '
        .($active
            ? 'bg-green-50 text-green-900'
            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    {{ $slot }}
</a>
