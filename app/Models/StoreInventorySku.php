<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StoreInventorySku extends Model
{
    protected $fillable = [
        'prod_cd',
        'is_active',
        'sort_order',
        'memo',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
