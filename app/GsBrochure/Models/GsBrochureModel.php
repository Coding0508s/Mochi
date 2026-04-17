<?php

namespace App\GsBrochure\Models;

use Illuminate\Database\Eloquent\Model;

class GsBrochureModel extends Model
{
    protected function prefixedTable(string $table): string
    {
        return (string) config('gs_brochure.table_prefix', 'gsb_').$table;
    }

    public function getConnectionName(): ?string
    {
        return (string) config('gs_brochure.connection', parent::getConnectionName());
    }
}
