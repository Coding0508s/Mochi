<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CoNewTarget extends Model
{
    protected $table = 'S_CO_NewTarget';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'Year',
        'CreatedDate',
        'AccountManager',
        'AccountCode',
        'AccountName',
        'Address',
        'Director',
        'Phone',
        'Connected',
        'Type',
        'Gubun',
        'LS',
        'GS_K',
        'GS_E',
        'Total',
        'Approaching',
        'Presenting',
        'Consulting',
        'Closing',
        'DroppedOut',
        'IsContract',
        'ContractedDate',
        'Possibility',
    ];

    protected function casts(): array
    {
        return [
            'CreatedDate' => 'datetime',
            'ContractedDate' => 'datetime',
            'IsContract' => 'boolean',
        ];
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if (blank($keyword)) {
            return $query;
        }

        $normalizedKeyword = preg_replace('/\s+/u', '', (string) $keyword) ?? '';
        if ($normalizedKeyword === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($normalizedKeyword) {
            $q->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                ->orWhereRaw("REPLACE(AccountCode, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                ->orWhereRaw("REPLACE(Address, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                ->orWhereRaw("REPLACE(Director, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
        });
    }
}

