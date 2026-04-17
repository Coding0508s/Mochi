<?php

namespace App\GsBrochure\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends GsBrochureModel
{
    protected $fillable = ['request_id', 'invoice_number'];

    public function getTable(): string
    {
        return $this->prefixedTable('invoices');
    }

    public function brochureRequest(): BelongsTo
    {
        return $this->belongsTo(BrochureRequest::class, 'request_id');
    }
}
