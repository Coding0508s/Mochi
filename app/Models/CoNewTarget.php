<?php

namespace App\Models;

use App\Support\ManagerNameNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'CreatedDate' => 'datetime',
            'ContractedDate' => 'datetime',
            'IsContract' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isManagedBy(User $user): bool
    {
        if ($user->hasFullAccess()) {
            return true;
        }

        if ((int) $this->created_by > 0) {
            return (int) $this->created_by === (int) $user->id;
        }

        // 표기 차이(공백/점)로 같은 사람이 어긋나지 않도록 InstitutionList와 같은 정규화 키로 비교.
        $managerKey = ManagerNameNormalizer::normalize((string) $this->AccountManager);
        $userKey = ManagerNameNormalizer::normalize((string) $user->name);

        return $managerKey !== '' && $userKey !== '' && $managerKey === $userKey;
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

