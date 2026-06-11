<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'event_date',
        'opening_date',
        'location',
        'price',
        'requires_receipt',
        'custom_fields',
        'status',
        'registrations',
        'image',
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
            'event_date' => 'datetime',
            'opening_date' => 'datetime',
            'price' => 'decimal:2',
            'requires_receipt' => 'boolean',
            'custom_fields' => 'array',
        ];
    }
}
