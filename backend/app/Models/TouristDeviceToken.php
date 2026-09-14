<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TouristDeviceToken extends Model
{
    protected $table = 'tourist_device_tokens';

    protected $fillable = [
        'card_id',
        'token',
        'platform',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function card()
    {
        return $this->belongsTo(TouristCard::class, 'card_id');
    }
}
