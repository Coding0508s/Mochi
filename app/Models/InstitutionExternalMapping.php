<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionExternalMapping extends Model
{
    protected $fillable = [
        'institution_id',
        'institution_name',
        'account_no',
        'sk_code',
        'erp_institution_name',
        'erp_account_no',
        'portal_campus_id',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'ID');
    }
}
