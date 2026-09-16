<x-mail-layout titulo="Inscrição confirmada" previa="Pagamento confirmado. Sua vaga está garantida!">

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
        <tr>
            <td style="background-color:#dcfce7; border-radius:999px; padding:6px 14px;">
                <span style="font-size:12px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; color:#166534;">
                    Pagamento confirmado
                </span>
            </td>
        </tr>
    </table>

    <h1 style="margin:0 0 12px 0; font-size:22px; font-weight:700; color:#111827;">
        Sua inscrição foi confirmada!!!
    </h1>

    <p style="margin:0 0 20px 0; font-size:15px; line-height:1.65; color:#374151;">
        Olá, {{ explode(' ', $inscricao->name)[0] }}. A tesouraria conferiu os dados da sua inscrição e ela foi confirmada!
    </p>

    @include('emails.partials.resumo')

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px 0;">
        <tr>
            <td style="background-color:#14532d; border-radius:8px;">
                <a href="{{ route('dashboard') }}" style="display:inline-block; padding:12px 22px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">
                    Ver minha inscrição
                </a>
            </td>
        </tr>
    </table>

</x-mail-layout>
