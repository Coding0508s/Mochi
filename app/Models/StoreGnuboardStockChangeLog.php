<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreGnuboardStockChangeLog extends Model
{
    protected $table = 'store_gnuboard_stock_change_logs';

    public const UPDATED_AT = null;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'product_code',
        'before_qty',
        'after_qty',
        'changed_by',
        'source',
        'memo',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
