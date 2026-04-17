<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = ['request_id', 'invoice_number'];

    public function brochureRequest(): BelongsTo
    {
        return $this->belongsTo(BrochureRequest::class, 'request_id');
    }
}
