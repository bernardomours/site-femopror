{{--
    Layout dos e-mails. Tabela e estilo inline de propósito: cliente de e-mail
    (Gmail, Outlook, Apple Mail) descarta <style> no head, ignora flexbox e
    reescreve classes. O que funciona em todos é tabela com width fixa.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'FEMOPROR' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f7f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1f2937;">

    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $previa ?? '' }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7f7f7; padding:32px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:12px;">

                    <tr>
                        <td style="padding:28px 32px 0 32px;">
                            <p style="margin:0; font-size:13px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:#14532d;">
                                FEMOPROR
                            </p>
                            <p style="margin:2px 0 0 0; font-size:12px; color:#9ca3af;">
                                Federação de Mocidades do Presbitério Oeste Rio-Grandense
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 32px 32px 32px;">
                            {{ $slot }}
                        </td>
                    </tr>

                </table>

                <p style="max-width:560px; margin:20px auto 0 auto; font-size:11px; line-height:1.6; color:#9ca3af; text-align:center;">
                    Você recebeu este e-mail porque se inscreveu em um evento da FEMOPROR.<br>
                    Dúvidas? Responda esta mensagem ou fale com a gente em
                    <a href="mailto:{{ config('femopror.contact.email') }}" style="color:#166534;">{{ config('femopror.contact.email') }}</a>.
                </p>

            </td>
        </tr>
    </table>

</body>
</html>
