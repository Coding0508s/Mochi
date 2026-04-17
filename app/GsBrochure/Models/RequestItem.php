<?php

namespace App\GsBrochure\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestItem extends GsBrochureModel
{
    protected $fillable = ['request_id', 'brochure_id', 'brochure_name', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function getTable(): string
    {
        return $this->prefixedTable('request_items');
    }

    public function brochureRequest(): BelongsTo
    {
        return $this->belongsTo(BrochureRequest::class, 'request_id');
    }

    public function brochure(): BelongsTo
    {
        return $this->belongsTo(Brochure::class, 'brochure_id');
    }
}
