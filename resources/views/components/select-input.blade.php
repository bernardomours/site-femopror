@props(['disabled' => false, 'name' => null])

@php
    $temErro = $name && $errors->has($name);

    $classes = 'block w-full appearance-none rounded-lg border bg-white px-3.5 py-2.5 pr-10 text-sm text-gray-900 transition-colors duration-150 outline-none disabled:cursor-not-allowed disabled:bg-gray-50 '
        .($temErro
            ? 'border-red-300 focus:border-red-500 focus:ring-1 focus:ring-red-500'
            : 'border-gray-200 hover:border-gray-300 focus:border-green-900 focus:ring-1 focus:ring-green-900');
@endphp

<div class="relative">
    <select
        @disabled($disabled)
        @if($name) name="{{ $name }}" @endif
        @if($temErro) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </select>

    {{-- Seta própria: a nativa muda de desenho em cada navegador. --}}
    <svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
</div>
