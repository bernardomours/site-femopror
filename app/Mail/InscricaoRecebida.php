<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sai assim que a pessoa envia a inscrição. Não é a confirmação — é o
 * comprovante de que o pedido chegou, enquanto a tesouraria confere o PIX.
 */
class InscricaoRecebida extends Mailable
{
    public function __construct(public Registration $inscricao) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recebemos sua inscrição · '.($this->inscricao->event?->title ?? 'Evento FEMOPROR'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inscricao-recebida',
            with: ['inscricao' => $this->inscricao->loadMissing(['event', 'church'])],
        );
    }
}
