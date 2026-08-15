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
        if ($user->hasPlatformWideViewAccess()) {
            return true;
        }

        if ((int) $this->created_by > 0) {
            return (int) $this->created_by === (int) $user->id;
        }

        // 이전 플랫폼 이관 행은 created_by가 없다.
        // 목록 스코프와 같이 영문명(nameForCoReports)을 쓰고, 로그인명도 허용한다.
        $managerKey = ManagerNameNormalizer::normalize((string) $this->AccountManager);
        if ($managerKey === '') {
            return false;
        }

        $reportKey = ManagerNameNormalizer::normalize($user->nameForCoReports());
        $loginKey = ManagerNameNormalizer::normalize((string) $user->name);

        return ($reportKey !== '' && $managerKey === $reportKey)
            || ($loginKey !== '' && $managerKey === $loginKey);
    }

    public function studentTotal(): int
    {
        return max(0, (int) ($this->LS ?? 0))
            + max(0, (int) ($this->GS_K ?? 0))
            + max(0, (int) ($this->GS_E ?? 0));
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
