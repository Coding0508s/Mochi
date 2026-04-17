<?php

namespace App\GsBrochure\Models;

class Institution extends GsBrochureModel
{
    protected $fillable = ['name', 'type', 'description', 'address', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return $this->prefixedTable('institutions');
    }
}
