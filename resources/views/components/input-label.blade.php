@props(['value' => null, 'optional' => false])

<label {{ $attributes->merge(['class' => 'mb-1.5 flex items-baseline justify-between gap-2 text-sm font-medium text-gray-800']) }}>
    <span>{{ $value ?? $slot }}</span>

    @if($optional)
        <span class="text-xs font-normal text-gray-400">opcional</span>
    @endif
</label>
