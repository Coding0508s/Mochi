<?php

namespace App\GsBrochure\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends GsBrochureModel
{
    protected $fillable = ['name'];

    public function getTable(): string
    {
        return $this->prefixedTable('contacts');
    }

    public function brochureRequests(): HasMany
    {
        return $this->hasMany(BrochureRequest::class, 'contact_id');
    }
}
