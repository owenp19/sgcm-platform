<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'user_id',
        'procedure_type_id',
        'office_id',
        'appointment_code',
        'scheduled_date',
        'scheduled_time',
        'status',
        'notes',
        'citizen_full_name',
        'citizen_document_type',
        'citizen_document_number',
        'citizen_email',
        'citizen_phone',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'scheduled_time' => 'string',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(AppointmentService::class, 'procedure_type_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function documents()
    {
        return $this->hasMany(AppointmentDocument::class, 'appointment_id');
    }
}
