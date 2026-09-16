<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'document_type',
        'file_path',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            // Era `is_federation`, copiado do model Church. A coluna que existe
            // nesta tabela é `is_active`.
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * O ícone é renderizado por @svg(). Nome inválido ou nulo vindo do banco
     * derrubava a home inteira com exceção, então cai no padrão.
     */
    public function iconName(): string
    {
        $permitidos = [
            'heroicon-o-document-text',
            'heroicon-o-book-open',
            'heroicon-o-musical-note',
            'heroicon-o-photo',
        ];

        return in_array($this->icon, $permitidos, true) ? $this->icon : 'heroicon-o-document-text';
    }
}
