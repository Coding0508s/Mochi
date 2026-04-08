<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDocument extends Model
{
    protected $fillable = [
        'sk_code',
        'account_name',
        'changed_account_name',
        'business_number',
        'document_date',
        'document_time',
        'consultant',
        'original_filename',
        'stored_disk',
        'stored_path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'sk_code', 'SKcode');
    }
}
