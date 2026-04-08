<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 사번(EMPNO)이 문자열 기본키입니다. 숫자로 자동 증가하지 않습니다.
 * 부서와 연결하려면 SQL의 department 테이블을 마이그레이션한 뒤
 * belongsTo(Department::class, 'WORKDEPT', 'DEPTNO') 관계를 추가하면 됩니다.
 */
class Employee extends Model
{
    protected $table = 'employee';

    protected $primaryKey = 'EMPNO';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'HIREDATE' => 'date',
            'FGC_CreateDate' => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
            'FGC_Rowversion' => 'datetime',
        ];
    }
}
