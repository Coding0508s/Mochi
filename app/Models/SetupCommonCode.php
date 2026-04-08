<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetupCommonCode extends Model
{
    protected $table = 'setup_common_codes';

    protected $fillable = [
        'category',
        'code',
        'label',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

