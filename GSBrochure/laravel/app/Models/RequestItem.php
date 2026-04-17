<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestItem extends Model
{
    protected $fillable = ['request_id', 'brochure_id', 'brochure_name', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function brochureRequest(): BelongsTo
    {
        return $this->belongsTo(BrochureRequest::class, 'request_id');
    }

    public function brochure(): BelongsTo
    {
        return $this->belongsTo(Brochure::class, 'brochure_id');
    }
}
