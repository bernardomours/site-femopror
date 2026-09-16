<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CongressSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'church_id',
        'status',
        'receipt_path',
        'notes',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function delegates(): HasMany
    {
        return $this->hasMany(Delegate::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CongressDocument::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'aprovado';
    }

    /**
     * Depois de aprovada, a inscrição é o documento que a secretaria conferiu.
     * Deixar a UMP trocar delegado ou comprovante depois disso reescreveria o
     * que já foi validado — o caminho é a secretaria voltar para "pendente".
     */
    public function isEditableByChurch(): bool
    {
        return ! $this->isApproved();
    }
}
