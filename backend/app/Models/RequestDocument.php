<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestDocument extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'user_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'file_path',
        'document_type',
        'is_validated',
        'validated_by',
        'validated_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_validated' => 'boolean',
            'validated_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function request()
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
