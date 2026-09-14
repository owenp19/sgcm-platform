<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PopulationZone extends Model
{
    protected $fillable = [
        'name',
        'zone_type',
        'latitude',
        'longitude',
        'total_population',
        'raizal_population',
        'other_population',
        'color',
        'description',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}