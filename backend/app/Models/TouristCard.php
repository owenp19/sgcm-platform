<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TouristCard extends Model
{
    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'document_type',
        'document_number',
        'birth_date',
        'nationality',
        'country_residence',
        'city_residence',
        'email',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'entry_date',
        'return_date',
        'transport_type',
        'airline_or_company',
        'flight_number',
        'origin_city',
        'travel_reason',
        'lodging_type',
        'lodging_name',
        'lodging_address',
        'lodging_sector',
        'lodging_phone',
        'lodging_responsible_name',
        'amount',
        'currency',
        'payment_status',
        'card_status',
        'qr_code',
        'receipt_url',
        'accepted_terms',
        'accepted_location_consent',
        'receipt_number',
        'payment_reference',
        'qr_token',
        'qr_validation_url',
        'issued_at',
        'expires_at',
        'language',
        'return_time',
        'notify_email',
        'notify_sms',
        'notify_push',
        'reminder_days_before',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'entry_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'accepted_terms' => 'boolean',
            'accepted_location_consent' => 'boolean',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'return_time' => 'string',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'notify_push' => 'boolean',
        ];
    }

    public function payments()
    {
        return $this->hasMany(TouristCardPayment::class, 'tourist_card_id');
    }

    public function receipts()
    {
        return $this->hasMany(TouristCardReceipt::class, 'tourist_card_id');
    }

    public function locationEvents()
    {
        return $this->hasMany(TouristLocationEvent::class, 'tourist_card_id');
    }
}
