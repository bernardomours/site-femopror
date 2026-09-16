<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Board extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'church_id',
        'president_name',
        'vice_president_name',
        'first_secretary_name',
        'second_secretary_name',
        'executive_secretary_name',
        'treasurer_name',
        'image_path',
        'is_active',
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
            'church_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        // A home mostra `$church->boards->first()` assumindo uma diretoria ativa
        // por igreja, mas nada garantia isso: marcar a diretoria nova sem
        // desmarcar a antiga deixava as duas ativas e a página exibia a que o
        // banco devolvesse primeiro — normalmente a mais velha.
        static::saved(function (self $board) {
            if (! $board->is_active) {
                return;
            }

            static::where('church_id', $board->church_id)
                ->whereKeyNot($board->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);
        });
    }
}
