<?php

namespace App\Support;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda e-mail sem deixar que uma falha de envio derrube a operação.
 *
 * A inscrição já está gravada quando o e-mail sai. Se o SMTP estiver fora do ar,
 * com a senha trocada ou com a cota estourada, deixar a exceção subir
 * transformaria "não avisamos por e-mail" em "a pessoa levou erro na tela e
 * achou que não se inscreveu" — e ela tentaria de novo, agora esbarrando no
 * índice único.
 *
 * A falha vai para o log (`storage/logs`), que é onde se procura quando alguém
 * diz que não recebeu.
 */
class SafeMail
{
    public static function send(string $destinatario, Mailable $mensagem): bool
    {
        if (blank($destinatario)) {
            return false;
        }

        try {
            Mail::to($destinatario)->send($mensagem);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
