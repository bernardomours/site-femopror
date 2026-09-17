<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'event_id',
        'church_id',
        'user_id',
        'name',
        'email',
        'phone',
        'payment_status',
        'payment_id',
        'pix_qr_code',
        'receipt_path',
        'custom_answers',
        'amount_paid',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'event_id' => 'integer',
            'church_id' => 'integer',
            'user_id' => 'integer',
            'amount_paid' => 'decimal:2',
            'custom_answers' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Quanto esta inscrição custa. `amount_paid` só existe desde que o valor
     * passou a ser congelado; inscrição mais antiga cai no preço do evento.
     */
    public function valorCobrado(): float
    {
        return (float) ($this->amount_paid ?? $this->event?->price ?? 0);
    }

    /**
     * Em que ponto a inscrição está. Uma coluna só (`payment_status`) não
     * responde isso desde que a inscrição passou a ser salva ANTES do
     * pagamento: "pendente" pode ser "ainda nem pagou" ou "pagou e mandou o
     * comprovante". O que separa os dois é ter ou não comprovante anexado — não
     * foi preciso coluna nova.
     */
    public function statusKey(): string
    {
        $valor = $this->valorCobrado();

        return match (true) {
            $this->payment_status === 'paid' => 'pago',
            $this->payment_status === 'failed' => 'cancelada',
            $valor <= 0 => 'gratuita',
            blank($this->receipt_path) && (bool) $this->event?->requiresReceiptFor($valor) => 'aguardando_comprovante',
            blank($this->receipt_path) => 'aguardando_pagamento',
            default => 'em_analise',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->statusKey()) {
            'pago' => 'Pagamento confirmado',
            'gratuita' => 'Inscrição confirmada',
            'cancelada' => 'Cancelada',
            'aguardando_comprovante' => 'Aguardando comprovante',
            'aguardando_pagamento' => 'Aguardando pagamento',
            default => 'Comprovante em análise',
        };
    }

    /** Cor semântica — os mesmos nomes do Filament (success, warning…). */
    public function statusColor(): string
    {
        return match ($this->statusKey()) {
            'pago', 'gratuita' => 'success',
            'cancelada' => 'danger',
            'em_analise' => 'info',
            default => 'warning',
        };
    }

    /**
     * Salva, mas ainda sem o comprovante que o evento exige. É o único estado
     * em que o participante pode voltar e alterar os dados: depois do
     * comprovante, mudar a modalidade mudaria o valor de um PIX já pago.
     */
    public function isAwaitingReceipt(): bool
    {
        return $this->statusKey() === 'aguardando_comprovante';
    }
}
