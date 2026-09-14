<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'document_type',
        'document_number',
        'phone',
        'photo_url',
        'reset_token',
        'reset_token_expires',
    ];

    protected $hidden = [
        'password',
        'reset_token',
        'reset_token_expires',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'reset_token_expires' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['email' => $this->email];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function getRoleNamesAttribute(): array
    {
        return $this->roles->pluck('name')->all();
    }

    public function hasRole(string ...$names): bool
    {
        return $this->roles->pluck('name')->intersect($names)->isNotEmpty();
    }

    public function preference()
    {
        return $this->hasOne(UserPreference::class, 'user_id');
    }

    public function requests()
    {
        return $this->hasMany(ServiceRequest::class, 'user_id');
    }

    public function assignedRequests()
    {
        return $this->hasMany(ServiceRequest::class, 'assigned_to');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class, 'user_id');
    }
}
