<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    // La tabla solo tiene created_at (no updated_at): un intento no se modifica
    public $timestamps = false;

    protected $fillable = [
        'identifier',
        'ip_address',
        'status',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
