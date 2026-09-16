{{-- Resumo da inscrição, compartilhado pelos dois e-mails. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
    <tr>
        <td style="padding:18px 20px;">

            <p style="margin:0 0 4px 0; font-size:11px; font-weight:600; letter-spacing:0.5px; text-transform:uppercase; color:#9ca3af;">Evento</p>
            <p style="margin:0 0 16px 0; font-size:17px; font-weight:700; color:#111827;">{{ $inscricao->event?->title ?? 'Evento FEMOPROR' }}</p>

            @if($inscricao->event?->event_date)
                <p style="margin:0 0 3px 0; font-size:14px; color:#374151;">
                    <strong style="color:#111827;">Quando:</strong> {{ $inscricao->event->event_date->format('d/m/Y \à\s H:i') }}
                </p>
            @endif

            @if($inscricao->event?->location)
                <p style="margin:0 0 3px 0; font-size:14px; color:#374151;">
                    <strong style="color:#111827;">Onde:</strong> {{ $inscricao->event->location }}
                </p>
            @endif

            @if($inscricao->church?->name)
                <p style="margin:0 0 3px 0; font-size:14px; color:#374151;">
                    <strong style="color:#111827;">Igreja:</strong> {{ $inscricao->church->name }}
                </p>
            @endif

            @if($inscricao->amount_paid !== null)
                <p style="margin:0; font-size:14px; color:#374151;">
                    <strong style="color:#111827;">Valor:</strong>
                    {{ (float) $inscricao->amount_paid > 0
                        ? 'R$ '.number_format((float) $inscricao->amount_paid, 2, ',', '.')
                        : 'Gratuito' }}
                </p>
            @endif

            @if(is_array($inscricao->custom_answers) && count(array_filter($inscricao->custom_answers)) > 0)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0 0 0; border-top:1px solid #e5e7eb;">
                    @foreach($inscricao->custom_answers as $pergunta => $resposta)
                        @if(filled($resposta))
                            <tr>
                                <td style="padding:10px 0 0 0;">
                                    <p style="margin:0 0 2px 0; font-size:11px; font-weight:600; letter-spacing:0.5px; text-transform:uppercase; color:#9ca3af;">{{ $pergunta }}</p>
                                    <p style="margin:0; font-size:14px; color:#111827;">{{ is_array($resposta) ? implode(', ', $resposta) : $resposta }}</p>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
            @endif

        </td>
    </tr>
</table>
