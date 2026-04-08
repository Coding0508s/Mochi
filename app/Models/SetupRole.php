<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetupRole extends Model
{
    protected $table = 'setup_roles';

    protected $fillable = [
        'role_key',
        'role_name',
        'description',
        'is_active',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }
}

