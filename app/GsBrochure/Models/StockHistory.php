<?php

namespace App\GsBrochure\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends GsBrochureModel
{
    protected $fillable = [
        'type',
        'location',
        'date',
        'brochure_id',
        'brochure_name',
        'quantity',
        'contact_name',
        'schoolname',
        'before_stock',
        'after_stock',
        'memo',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'before_stock' => 'integer',
        'after_stock' => 'integer',
    ];

    public function getTable(): string
    {
        return $this->prefixedTable('stock_histories');
    }

    public function brochure(): BelongsTo
    {
        return $this->belongsTo(Brochure::class, 'brochure_id');
    }
}
