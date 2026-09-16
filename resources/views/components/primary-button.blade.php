{{--
    Verde chapado, canto discreto, sem gradiente e sem uppercase apertado.
    O estado de foco é um anel fino — não um halo.
--}}
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center justify-center gap-2 rounded-lg bg-green-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors duration-150 hover:bg-green-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-900 focus-visible:ring-offset-2 active:bg-green-950 disabled:cursor-not-allowed disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>
