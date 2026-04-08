<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CoNewTargetDetail extends Model
{
    protected $table = 'S_CO_NewTarget_Detail';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'Year',
        'AccountName',
        'AccountManager',
        'MeetingDate',
        'MeetingTime',
        'MeetingTime_End',
        'Description',
        'ConsultingType',
        'Possibility',
    ];

    protected function casts(): array
    {
        return [
            'MeetingDate' => 'datetime',
        ];
    }

    public function scopeOfAccount(Builder $query, ?string $accountName): Builder
    {
        if (blank($accountName)) {
            return $query;
        }

        return $query->where('AccountName', $accountName);
    }
}

