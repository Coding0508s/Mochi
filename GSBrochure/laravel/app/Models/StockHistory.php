<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    protected $table = 'stock_history';

    protected $fillable = [
        'type', 'location', 'date', 'brochure_id', 'brochure_name', 'quantity',
        'contact_name', 'schoolname', 'before_stock', 'after_stock', 'memo',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'before_stock' => 'integer',
        'after_stock' => 'integer',
    ];

    public function brochure(): BelongsTo
    {
        return $this->belongsTo(Brochure::class, 'brochure_id');
    }
}
