<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function delegates()
    {
        return $this->hasMany(Delegate::class);
    }

    public function documents()
    {
        return $this->hasMany(CongressDocument::class);
    }
}