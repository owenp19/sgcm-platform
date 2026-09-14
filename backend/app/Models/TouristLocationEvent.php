<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TouristLocationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tourist_card_id',
        'latitude',
        'longitude',
        'accuracy',
        'reason',
        'captured_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'captured_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
