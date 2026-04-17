<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brochure extends Model
{
    protected $fillable = [
        'name', 'image_url', 'stock', 'stock_warehouse',
        'last_stock_quantity', 'last_stock_date',
        'last_warehouse_stock_quantity', 'last_warehouse_stock_date',
    ];

    protected $casts = [
        'stock' => 'integer',
        'stock_warehouse' => 'integer',
        'last_stock_quantity' => 'integer',
        'last_warehouse_stock_quantity' => 'integer',
    ];

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'brochure_id');
    }

    public function stockHistory(): HasMany
    {
        return $this->hasMany(StockHistory::class, 'brochure_id');
    }
}
