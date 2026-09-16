<x-mail-layout titulo="Recebemos sua inscrição" previa="Sua inscrição chegou. Agora a tesouraria vai conferir o comprovante.">

    <h1 style="margin:0 0 12px 0; font-size:22px; font-weight:700; color:#111827;">
        Recebemos sua inscrição!
    </h1>

    <p style="margin:0 0 20px 0; font-size:15px; line-height:1.65; color:#374151;">
        Oi, {{ explode(' ', $inscricao->name)[0] }}. Sua inscrição chegou aqui certinho.
        @if((float) $inscricao->amount_paid > 0)
            Agora a tesouraria vai conferir o comprovante do PIX — assim que confirmar, você
            recebe outro e-mail avisando.
        @else
            Sua vaga já está registrada.
        @endif
    </p>

    @include('emails.partials.resumo')

    @if((float) $inscricao->amount_paid > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0; background-color:#fffbeb; border:1px solid #fde68a; border-radius:8px;">
            <tr>
                <td style="padding:14px 18px;">
                    <p style="margin:0; font-size:14px; line-height:1.6; color:#92400e;">
                        <strong>Situação: em análise.</strong> A conferência do comprovante é feita
                        manualmente pela tesouraria e costuma levar alguns dias.
                    </p>
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px 0;">
        <tr>
            <td style="background-color:#14532d; border-radius:8px;">
                <a href="{{ route('dashboard') }}" style="display:inline-block; padding:12px 22px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">
                    Acompanhar minha inscrição
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:16px 0 0 0; font-size:13px; line-height:1.6; color:#6b7280;">
        Não foi você que fez esta inscrição? Responda este e-mail que a gente resolve.
    </p>

</x-mail-layout>
