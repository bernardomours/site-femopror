<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CongressDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'congress_subscription_id',
        'document_type',
        'file_path',
    ];

    public function subscription()
    {
        return $this->belongsTo(CongressSubscription::class, 'congress_subscription_id');
    }
}