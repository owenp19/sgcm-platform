<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TouristCardReceipt extends Model
{
    protected $table = 'tourist_card_receipts';

    protected $fillable = [
        'tourist_card_id',
        'receipt_number',
        'pdf_path',
        'qr_payload',
        'qr_token',
        'issued_at',
        'expires_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function card()
    {
        return $this->belongsTo(TouristCard::class, 'tourist_card_id');
    }
}
