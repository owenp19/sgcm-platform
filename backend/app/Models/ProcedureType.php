<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedureType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'requirements',
        'base_cost',
        'estimated_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'base_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function requests()
    {
        return $this->hasMany(ServiceRequest::class, 'procedure_type_id');
    }
}
