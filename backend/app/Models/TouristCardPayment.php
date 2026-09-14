<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TouristCardPayment extends Model
{
    protected $fillable = [
        'tourist_card_id',
        'payment_reference',
        'gateway',
        'amount',
        'currency',
        'status',
        'gateway_response',
        'paid_at',
        'receipt_number',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function touristCard()
    {
        return $this->belongsTo(TouristCard::class, 'tourist_card_id');
    }
}
