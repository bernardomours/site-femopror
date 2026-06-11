<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delegate extends Model
{
    use HasFactory;

    protected $fillable = [
        'congress_subscription_id',
        'name',
        'email',
        'type',
    ];

    public function subscription()
    {
        return $this->belongsTo(CongressSubscription::class, 'congress_subscription_id');
    }
}