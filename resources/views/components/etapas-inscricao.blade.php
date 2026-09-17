@props(['atual' => 1])

{{-- Indicador "① Seus dados — ② Pagamento". Só aparece quando há algo a pagar. --}}
<ol class="mb-6 flex items-center gap-3 text-xs font-semibold" aria-label="Etapas da inscrição">
    <li class="flex items-center gap-2 {{ $atual === 1 ? 'text-green-900' : 'text-gray-400' }}" @if($atual === 1) aria-current="step" @endif>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $atual === 1 ? 'bg-green-900 text-white' : 'bg-green-100 text-green-900' }}">
            @if($atual > 1)
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            @else
                1
            @endif
        </span>
        Seus dados
    </li>

    <li class="h-px flex-1 bg-gray-200" aria-hidden="true"></li>

    <li class="flex items-center gap-2 {{ $atual === 2 ? 'text-green-900' : 'text-gray-400' }}" @if($atual === 2) aria-current="step" @endif>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $atual === 2 ? 'bg-green-900 text-white' : 'bg-gray-100 text-gray-400' }}">2</span>
        Pagamento
    </li>
</ol>
