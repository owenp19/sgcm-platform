<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentDocument extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'file_path',
        'document_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
