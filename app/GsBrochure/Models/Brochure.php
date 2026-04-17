<?php

namespace App\GsBrochure\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Brochure extends GsBrochureModel
{
    protected $fillable = [
        'name',
        'image_url',
        'stock',
        'stock_warehouse',
        'last_stock_quantity',
        'last_stock_date',
        'last_warehouse_stock_quantity',
        'last_warehouse_stock_date',
    ];

    protected $casts = [
        'stock' => 'integer',
        'stock_warehouse' => 'integer',
        'last_stock_quantity' => 'integer',
        'last_warehouse_stock_quantity' => 'integer',
    ];

    public function getTable(): string
    {
        return $this->prefixedTable('brochures');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'brochure_id');
    }

    public function stockHistory(): HasMany
    {
        return $this->hasMany(StockHistory::class, 'brochure_id');
    }
}
