<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 외부 시스템(Forguncy 등)에서 내려온 학교(기관) 마스터.
 * 실제 조인은 대개 숫자 PK(ID)가 아니라 business key인 schoolId로 합니다.
 */
class GsSchool extends Model
{
    protected $table = 'GS_schools';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $guarded = [];

    public function students(): HasMany
    {
        return $this->hasMany(GsStudent::class, 'schoolId', 'schoolId');
    }
}
