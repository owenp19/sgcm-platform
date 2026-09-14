<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourismTariff extends Model
{
    protected $fillable = [
        'concept',
        'amount',
        'currency',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
