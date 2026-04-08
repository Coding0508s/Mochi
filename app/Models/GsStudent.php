<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GsStudent extends Model
{
    protected $table = 'GS_students';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birthday' => 'datetime',
            'FGC_CreateDate' => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
            'FGC_Rowversion' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(GsSchool::class, 'schoolId', 'schoolId');
    }
}
