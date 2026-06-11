<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
            'is_federation' => 'boolean',
        ];
    }
}
