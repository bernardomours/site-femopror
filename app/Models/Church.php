<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Church extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_federation',
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
            'is_federation' => 'boolean',
        ];
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function congressSubscriptions(): HasMany
    {
        return $this->hasMany(CongressSubscription::class);
    }

    /** Igrejas que compõem a federação. */
    public function scopeOfFederation(Builder $query): Builder
    {
        return $query->where('is_federation', true);
    }

    /**
     * "Igreja Presbiteriana do Planalto" tem que ordenar por "planalto".
     * Isso era um orderByRaw com cinco REPLACE aninhados dentro do Blade;
     * em PHP a regra fica legível e não depende do dialeto do banco.
     */
    public function sortableName(): string
    {
        $nome = Str::lower($this->name);
        $nome = preg_replace('/^igreja\s+(presbiteriana\s+)?/u', '', $nome);
        $nome = preg_replace('/\b(d[aeo]s?)\s+/u', '', $nome);

        return trim($nome ?? '');
    }
}
