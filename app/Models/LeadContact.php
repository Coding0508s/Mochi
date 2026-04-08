<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_id',
        'name',
        'position',
        'role',
        'phone',
        'mobile',
        'email',
        'is_primary',
        'memo',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
