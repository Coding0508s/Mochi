<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = ['name'];

    public function brochureRequests(): HasMany
    {
        return $this->hasMany(BrochureRequest::class, 'contact_id');
    }
}
