<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TouristReturnReminder extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tourist_card_id',
        'reminder_type',
        'remind_at',
        'sent',
        'sent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sent' => 'boolean',
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
