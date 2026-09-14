<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'requires_documents',
        'duration_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_documents' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
