<?php

namespace App\Models;

use App\Support\ManagerNameNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ═══════════════════════════════════════════════════════════════
 * [기관-담당자 배정] AccountInformation 모델
 * ───────────────────────────────────────────────────────────────
 * 데이터베이스 테이블: S_Account_Information
 *
 * 기관 하나에 어떤 담당자(TR·CS·CO)가 배정되었는지 기록합니다.
 * Institution 모델에서 accountInfo() 관계를 통해 접근합니다.
 * ═══════════════════════════════════════════════════════════════
 *
 * @property int $ID
 * @property string $SK_Code 기관 SK 코드 (Institution.SKcode 와 연결)
 * @property string $Account_Name 기관명
 * @property string $TR 담당 TR 이름
 * @property string $CS 담당 CS 이름
 * @property string $CO 담당 CO 이름
 * @property string $Customer_Type 고객 유형
 * @property string $Affiliate 가맹/제휴 정보
 * @property string $Address 주소
 * @property Carbon|null $FGC_CreateDate
 * @property Carbon|null $FGC_LastModifyDate
 */
class AccountInformation extends Model
{
    protected $table = 'S_Account_Information';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'SK_Code',
        'Account_Name',
        'TR',
        'CS',
        'CO',
        'Customer_Type',
        'Affiliate',
        'Address',
    ];

    protected function casts(): array
    {
        return [
            'FGC_CreateDate' => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
        ];
    }

    // ─── 관계 ─────────────────────────────────────────────────────────

    /**
     * 이 배정 정보가 속한 기관
     *
     * 사용 예:
     *   $info->institution->AccountName  // 기관명 출력
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'SK_Code', 'SKcode');
        // "S_AccountName 테이블에서 SKcode = 이 행의 SK_Code 인 기관을 가져와"
    }

    /**
     * 기관명·SK코드·담당자·주소 등 S_Account_Information 컬럼 기준 검색.
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (blank($keyword)) {
            return $query;
        }

        $normalizedKeyword = preg_replace('/\s+/u', '', (string) $keyword) ?? '';
        if ($normalizedKeyword === '') {
            return $query;
        }

        return $query->where(function (Builder $accountQuery) use ($normalizedKeyword): void {
            foreach (['SK_Code', 'Account_Name', 'CO', 'TR', 'CS', 'Customer_Type', 'Affiliate', 'Address'] as $column) {
                $accountQuery->orWhereRaw("REPLACE({$column}, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
            }

            $accountQuery->orWhereHas('institution', function (Builder $institutionQuery) use ($normalizedKeyword): void {
                $institutionQuery->where(function (Builder $masterQuery) use ($normalizedKeyword): void {
                    foreach (['AccountName', 'SKcode', 'Director', 'Address', 'EnglishName'] as $column) {
                        $masterQuery->orWhereRaw("REPLACE({$column}, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
                    }
                });
            });
        });
    }

    public function isTerminatedCustomer(): bool
    {
        return str_contains((string) ($this->Customer_Type ?? ''), '해지');
    }

    public function isTerminatedThisCalendarYear(?int $year = null): bool
    {
        if (! $this->isTerminatedCustomer()) {
            return false;
        }

        $modifiedAt = $this->FGC_LastModifyDate;
        if ($modifiedAt === null) {
            return false;
        }

        return (int) $modifiedAt->year === ($year ?? (int) now()->year);
    }

    public function currentYearTerminationBadgeLabel(?int $year = null): ?string
    {
        if (! $this->isTerminatedThisCalendarYear($year) || $this->FGC_LastModifyDate === null) {
            return null;
        }

        return $this->FGC_LastModifyDate->format('y').'년 해지';
    }

    public function scopeActiveCustomers(Builder $query): Builder
    {
        return $query->where(function (Builder $statusQuery): void {
            $statusQuery->whereNull('Customer_Type')
                ->orWhere('Customer_Type', '')
                ->orWhere('Customer_Type', 'not like', '%해지%');
        });
    }

    /**
     * 운영 기관 + 해당 연도에 해지된 기관.
     * 해지 연도는 S_Account_Information.FGC_LastModifyDate 기준입니다.
     */
    public function scopeActiveCustomersIncludingTerminatedThisYear(Builder $query, ?int $year = null): Builder
    {
        $year ??= (int) now()->year;

        return $query->where(function (Builder $statusQuery) use ($year): void {
            $statusQuery->where(function (Builder $activeQuery): void {
                $activeQuery->whereNull('Customer_Type')
                    ->orWhere('Customer_Type', '')
                    ->orWhere('Customer_Type', 'not like', '%해지%');
            })->orWhere(function (Builder $thisYearTerminatedQuery) use ($year): void {
                $thisYearTerminatedQuery->where('Customer_Type', 'like', '%해지%')
                    ->whereYear('FGC_LastModifyDate', $year);
            });
        });
    }

    public function scopeTerminatedCustomers(Builder $query): Builder
    {
        return $query->where('Customer_Type', 'like', '%해지%');
    }

    public function scopeWhereManagerAssigned(Builder $query, string $column): Builder
    {
        return $query->whereNotNull($column)
            ->where($column, '!=', '');
    }

    /**
     * @param  list<string>  $aliases
     */
    public function scopeWhereManagerMatches(Builder $query, string $column, array $aliases): Builder
    {
        if ($aliases === []) {
            return $query->whereRaw('1 = 0');
        }

        $sqlNormalized = ManagerNameNormalizer::sqlColumnExpression($column);

        return $query->where(function (Builder $managerQuery) use ($aliases, $sqlNormalized): void {
            foreach ($aliases as $alias) {
                $managerQuery->orWhereRaw("{$sqlNormalized} = ?", [$alias]);
            }
        });
    }
}
