<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'department';

    protected $primaryKey = 'DEPTNO';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'DEPTNO',
        'DEPTNAME',
        'MGRNO',
        'ADMRDEPT',
        'LOCATION',
        'FGC_CreateDate',
        'FGC_LastModifier',
        'FGC_LastModifyDate',
        'FGC_Creator',
    ];
}

