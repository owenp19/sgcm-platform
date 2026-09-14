<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $table = 'requests';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'tracking_number',
        'user_id',
        'procedure_type_id',
        'assigned_to',
        'status',
        'priority',
        'notes',
        'internal_notes',
        'submitted_at',
        'resolved_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function procedureType()
    {
        return $this->belongsTo(ProcedureType::class, 'procedure_type_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function documents()
    {
        return $this->hasMany(RequestDocument::class, 'request_id');
    }

    public function history()
    {
        return $this->hasMany(RequestHistory::class, 'request_id');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('funcionario', 'admin')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
