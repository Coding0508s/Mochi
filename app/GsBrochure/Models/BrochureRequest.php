<?php

namespace App\GsBrochure\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrochureRequest extends GsBrochureModel
{
    protected $fillable = [
        'date',
        'schoolname',
        'address',
        'phone',
        'contact_id',
        'contact_name',
    ];

    public function getTable(): string
    {
        return $this->prefixedTable('requests');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'request_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'request_id');
    }
}
