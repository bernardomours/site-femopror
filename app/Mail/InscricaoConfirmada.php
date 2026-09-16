<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sai quando a tesouraria confirma que o PIX caiu. É este que a pessoa espera:
 * o "sua vaga está garantida".
 */
class InscricaoConfirmada extends Mailable
{
    public function __construct(public Registration $inscricao) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inscrição confirmada · '.($this->inscricao->event?->title ?? 'Evento FEMOPROR'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inscricao-confirmada',
            with: ['inscricao' => $this->inscricao->loadMissing(['event', 'church'])],
        );
    }
}
