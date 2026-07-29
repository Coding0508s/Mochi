<?php

namespace App\Models;

use Database\Factories\StoreReturnRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReturnRegistration extends Model
{
    /** @use HasFactory<StoreReturnRegistrationFactory> */
    use HasFactory;

    protected $fillable = [
        'returned_at',
        'institution_sk_code',
        'institution_name',
        'item_name',
        'quantity',
        'status',
        'freight',
        'notes',
        'cs_memo',
        'cs_team',
        'registered_by',
        'registration_group_key',
    ];

    protected function casts(): array
    {
        return [
            'returned_at' => 'date',
            'quantity' => 'integer',
        ];
    }

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
