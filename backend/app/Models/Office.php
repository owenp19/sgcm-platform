<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'hours_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hours_config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
