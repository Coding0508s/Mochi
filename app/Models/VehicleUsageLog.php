<?php

namespace App\Models;

use Database\Factories\VehicleUsageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleUsageLog extends Model
{
    /** @use HasFactory<VehicleUsageLogFactory> */
    use HasFactory;

    protected $fillable = [
        'shared_supply_id',
        'user_id',
        'vehicle_name',
        'usage_purpose_name',
        'odometer_before',
        'odometer_after',
        'distance',
        'arrival_location',
        'remarks',
        'driven_on',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'driven_on' => 'date',
        ];
    }

    public function sharedSupply(): BelongsTo
    {
        return $this->belongsTo(SharedSupply::class, 'shared_supply_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
