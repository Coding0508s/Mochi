<?php

namespace App\Models;

use App\Casts\NormalizedMultilineText;
use App\Support\ExcelSerialDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════
 * [기관 지원 내역] SupportRecord 모델
 * ───────────────────────────────────────────────────────────────
 * 데이터베이스 테이블: S_SupportInfo_Account
 *
 * PRD 4.3 '기관 지원 내역' 화면과 '보고서 작성' 모달의 핵심 모델입니다.
 *
 * 사용 예시:
 *   // 2025년도 전체 지원 내역
 *   SupportRecord::ofYear(2025)->get()
 *
 *   // 특정 기관의 완료된 지원만
 *   SupportRecord::ofInstitution('SK001')->completed()->get()
 *
 *   // 새 지원 기록 저장
 *   SupportRecord::create([...])
 *
 *   // 이 지원 기록이 속한 기관 정보
 *   $record->institution->AccountName
 * ═══════════════════════════════════════════════════════════════
 *
 * @property int $ID
 * @property int $Year 연도
 * @property string $SK_Code 기관 코드
 * @property string $Account_Name 기관명
 * @property string $TR_Name 담당 TR 이름
 * @property string $Support_Date 지원 날짜
 * @property string $Support_Type 지원 방식
 * @property string $Issue 이슈 내용
 * @property string $TO_Account 기관 소통 내용
 * @property string $Status 처리 상태
 */
class SupportRecord extends Model
{
    public const STATUS_COMPLETED = '완료';

    public const STATUS_IN_PROGRESS = '진행중';

    // ─── 테이블 설정 ──────────────────────────────────────────────────
    protected $table = 'S_SupportInfo_Account';

    protected $primaryKey = 'ID';

    public $timestamps = false;
    // Laravel 기본 timestamps(created_at/updated_at) 대신
    // FGC_CreateDate 와 CreatedDate 를 직접 관리합니다.

    // ─── 대량 입력 허용 필드 (보고서 작성 모달에서 저장할 항목들) ────────
    protected $fillable = [
        'Year',
        'SK_Code',
        'potential_target_id',
        'Account_Name',
        'TR_Name',
        'Support_Date',
        'Meet_Time',
        'Target',
        'Support_Type',
        'Issue',
        'Others',
        'TO_Account',    // PRD: "기관과의 소통내용"
        'TO_Depart',
        'Status',
        'dePart',
        'CreatedDate',
        'CompletedDate',
        'is_urgent',
        'record_kind',
    ];

    /** record_kind 값: CS 기관 이슈 */
    public const KIND_ISSUE = 'issue';

    // ─── 날짜/타입 자동 변환 ──────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'Support_Date' => 'datetime',
            // 꺼내올 때 자동으로 날짜 객체가 됩니다.
            // 예: $record->Support_Date->format('Y년 m월 d일')

            'Meet_Time' => 'datetime',
            // 시간 필드도 마찬가지입니다.

            'CreatedDate' => 'datetime',
            'CompletedDate' => 'datetime',
            'FGC_CreateDate' => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
            'FGC_Rowversion' => 'datetime',
            'Issue' => NormalizedMultilineText::class,
            'TO_Account' => NormalizedMultilineText::class,
            'TO_Depart' => NormalizedMultilineText::class,
            'Others' => NormalizedMultilineText::class,
            'is_urgent' => 'boolean',
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // 관계(Relationship) 정의
    // ════════════════════════════════════════════════════════════════

    /**
     * 이 지원 기록이 속한 기관
     *
     * 사용 예:
     *   $record->institution->AccountName  // "○○유치원"
     *   $record->institution->Director     // 원장 이름
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'SK_Code', 'SKcode');
    }

    /**
     * 잠재기관(미계약) 보고서 연결
     */
    public function potentialTarget(): BelongsTo
    {
        return $this->belongsTo(CoNewTarget::class, 'potential_target_id', 'ID');
    }

    // ════════════════════════════════════════════════════════════════
    // 검색용 스코프 (PRD 4.3 필터 기능들)
    // ════════════════════════════════════════════════════════════════

    /**
     * 연도별 필터 (PRD 4.3 상단 년도 필터)
     *
     * 사용 예:
     *   SupportRecord::ofYear(2025)->get()
     */
    /**
     * @return list<string>
     */
    public static function tableColumns(): array
    {
        return once(function (): array {
            $table = (new static)->getTable();

            if (! Schema::hasTable($table)) {
                return [];
            }

            return Schema::getColumnListing($table);
        });
    }

    public static function tableHasColumn(string $column): bool
    {
        return in_array($column, static::tableColumns(), true);
    }

    public static function tableHasYearColumn(): bool
    {
        return static::tableHasColumn('Year');
    }

    /**
     * 연도 필터·distinct 목록에 쓸 수 있는 컬럼 (우선순위 순).
     *
     * 목록의 지원일(Support_Date)과 필터 기준을 맞추기 위해 Year보다 Support_Date를 우선합니다.
     */
    public static function yearSourceColumn(): ?string
    {
        foreach (['Support_Date', 'Year', 'CreatedDate', 'FGC_CreateDate'] as $column) {
            if (static::tableHasColumn($column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * 레거시 DB처럼 컬럼이 없는 환경에서 mass assignment/update 오류를 막습니다.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function filterAttributesForTable(array $attributes): array
    {
        foreach (array_keys($attributes) as $column) {
            if (! static::tableHasColumn($column)) {
                unset($attributes[$column]);
            }
        }

        return $attributes;
    }

    /**
     * 연도 필터·드롭다운용 SQL — Support_Date(엑셀 serial 포함) 우선, 비어 있으면 Year.
     */
    public static function sqlResolvedFilterYearExpression(): ?string
    {
        if (static::tableHasColumn('Support_Date')) {
            $normalizedDate = ExcelSerialDate::sqlNormalizedDateColumn('Support_Date');
            $driver = Schema::getConnection()->getDriverName();
            $yearFromSupportDate = match ($driver) {
                'sqlite' => "CAST(strftime('%Y', {$normalizedDate}) AS INTEGER)",
                default => "YEAR({$normalizedDate})",
            };

            if (! static::tableHasColumn('Year')) {
                return $yearFromSupportDate;
            }

            $blankSafeSupportDate = ExcelSerialDate::sqlBlankSafeText('Support_Date');

            return "CASE WHEN {$blankSafeSupportDate} IS NOT NULL THEN {$yearFromSupportDate} ELSE ".static::sqlIntegerCast('Year').' END';
        }

        if (static::tableHasColumn('Year')) {
            return static::sqlIntegerCast('Year');
        }

        $column = static::yearSourceColumn();
        if ($column === null) {
            return null;
        }

        return static::yearExpressionForColumn($column);
    }

    /**
     * 연도 필터 드롭다운용 distinct 연도 목록.
     *
     * @return Collection<int, int>
     */
    public static function distinctFilterYears(): Collection
    {
        $yearExpression = static::sqlResolvedFilterYearExpression();

        if ($yearExpression === null) {
            return collect();
        }

        return static::query()
            ->excludeIssues()
            ->selectRaw("{$yearExpression} as filter_year")
            ->whereRaw("({$yearExpression}) IS NOT NULL")
            ->whereRaw("({$yearExpression}) > 0")
            ->distinct()
            ->orderByDesc('filter_year')
            ->pluck('filter_year')
            ->map(fn (mixed $year): int => (int) $year)
            ->filter(fn (int $year): bool => $year > 0)
            ->values();
    }

    private static function yearExpressionForColumn(string $column): string
    {
        $quoted = '`'.str_replace('`', '``', $column).'`';

        return match (Schema::getConnection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', {$quoted}) AS INTEGER)",
            default => "YEAR({$quoted})",
        };
    }

    /**
     * SQLite는 INTEGER, MySQL/MariaDB는 SIGNED·UNSIGNED만 CAST 타입으로 허용.
     */
    private static function sqlIntegerCast(string $expression): string
    {
        return match (Schema::getConnection()->getDriverName()) {
            'sqlite' => "CAST({$expression} AS INTEGER)",
            default => "CAST({$expression} AS UNSIGNED)",
        };
    }

    public function scopeOfYear(Builder $query, ?int $year): Builder
    {
        if (blank($year)) {
            return $query;
        }

        $yearExpression = static::sqlResolvedFilterYearExpression();

        if ($yearExpression === null) {
            return $query;
        }

        return $query->whereRaw("({$yearExpression}) = ?", [$year]);
    }

    public function scopeOrderedForList(Builder $query): Builder
    {
        if (static::tableHasColumn('CreatedDate')) {
            $query->orderByRaw('CreatedDate IS NULL ASC')
                ->orderByDesc('CreatedDate');
        } elseif (static::tableHasColumn('FGC_CreateDate')) {
            $query->orderByRaw('FGC_CreateDate IS NULL ASC')
                ->orderByDesc('FGC_CreateDate');
        }

        if (static::tableHasColumn('ID')) {
            $query->orderByDesc('ID');
        }

        return $query;
    }

    public function scopeWithInstitutionWhenPossible(Builder $query): Builder
    {
        if (static::tableHasColumn('SK_Code')) {
            return $query->with('institution');
        }

        return $query;
    }

    /**
     * 특정 기관의 지원 내역만 조회 (PRD 4.3 기관 필터)
     *
     * 사용 예:
     *   SupportRecord::ofInstitution('SK001')->get()
     */
    public function scopeOfInstitution(Builder $query, ?string $skCode): Builder
    {
        if (blank($skCode) || ! static::tableHasColumn('SK_Code')) {
            return $query;
        }

        return $query->where('SK_Code', $skCode);
    }

    /**
     * 특정 담당자의 지원 내역만 조회 (PRD 4.3 담당 필터)
     *
     * 사용 예:
     *   SupportRecord::ofTr('홍길동')->get()
     */
    public function scopeOfTr(Builder $query, ?string $trName): Builder
    {
        if (blank($trName) || ! static::tableHasColumn('TR_Name')) {
            return $query;
        }

        return $query->where('TR_Name', $trName);
    }

    /**
     * 완료 처리된 지원 내역만 조회
     *
     * 사용 예:
     *   SupportRecord::completed()->get()
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return static::applyCompletedScope($query);
    }

    /**
     * 아직 완료되지 않은 지원 내역 (진행 중)
     *
     * 사용 예:
     *   SupportRecord::inProgress()->get()
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return static::applyInProgressScope($query);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        if (! static::tableHasColumn('is_urgent')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('is_urgent', true);
    }

    /**
     * CS 기관 이슈만 조회 (record_kind = 'issue').
     */
    public function scopeOnlyIssues(Builder $query): Builder
    {
        if (! static::tableHasColumn('record_kind')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('record_kind', self::KIND_ISSUE);
    }

    /**
     * 기관 이슈를 제외한 일반 기관 지원 보고서만 조회.
     */
    public function scopeExcludeIssues(Builder $query): Builder
    {
        if (! static::tableHasColumn('record_kind')) {
            return $query;
        }

        return $query->where(function (Builder $inner): void {
            $inner->whereNull('record_kind')
                ->orWhere('record_kind', '!=', self::KIND_ISSUE);
        });
    }

    public function isIssue(): bool
    {
        return static::tableHasColumn('record_kind')
            && (string) ($this->record_kind ?? '') === self::KIND_ISSUE;
    }

    /**
     * 레거시(Status)와 MOCHI(CompletedDate) 완료 기준을 통일합니다.
     */
    public function isCompleted(): bool
    {
        if (static::tableHasColumn('CompletedDate') && $this->CompletedDate !== null) {
            return true;
        }

        if (static::tableHasColumn('Status')) {
            return (string) ($this->Status ?? '') === self::STATUS_COMPLETED;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function completionAttributes(bool $completed, ?\DateTimeInterface $completedAt = null): array
    {
        $attributes = [];

        if (static::tableHasColumn('Status')) {
            $attributes['Status'] = $completed ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS;
        }

        if (static::tableHasColumn('CompletedDate')) {
            $attributes['CompletedDate'] = $completed ? ($completedAt ?? now()) : null;
        }

        return $attributes;
    }

    public static function applyCompletedScope(Builder $query): Builder
    {
        $hasCompletedDate = static::tableHasColumn('CompletedDate');
        $hasStatus = static::tableHasColumn('Status');

        if (! $hasCompletedDate && ! $hasStatus) {
            return $query->whereRaw('1 = 0');
        }

        if ($hasCompletedDate && ! $hasStatus) {
            return $query->whereNotNull('CompletedDate');
        }

        if (! $hasCompletedDate && $hasStatus) {
            return $query->where('Status', self::STATUS_COMPLETED);
        }

        return $query->where(function (Builder $completedQuery): void {
            $completedQuery->whereNotNull('CompletedDate')
                ->orWhere('Status', self::STATUS_COMPLETED);
        });
    }

    public static function applyInProgressScope(Builder $query): Builder
    {
        $hasCompletedDate = static::tableHasColumn('CompletedDate');
        $hasStatus = static::tableHasColumn('Status');

        if (! $hasCompletedDate && ! $hasStatus) {
            return $query;
        }

        if ($hasCompletedDate && ! $hasStatus) {
            return $query->whereNull('CompletedDate');
        }

        if (! $hasCompletedDate && $hasStatus) {
            return $query->where(function (Builder $inProgressQuery): void {
                $inProgressQuery->whereNull('Status')
                    ->orWhere('Status', '!=', self::STATUS_COMPLETED);
            });
        }

        return $query->where(function (Builder $inProgressQuery): void {
            $inProgressQuery->whereNull('CompletedDate')
                ->where(function (Builder $statusQuery): void {
                    $statusQuery->whereNull('Status')
                        ->orWhere('Status', '!=', self::STATUS_COMPLETED);
                });
        });
    }

    /**
     * 기관명 또는 이슈 내용으로 키워드 검색
     *
     * 사용 예:
     *   SupportRecord::keyword('앱 사용률')->get()
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if (blank($keyword)) {
            return $query;
        }

        $normalizedKeyword = preg_replace('/\s+/u', '', (string) $keyword) ?? '';
        if ($normalizedKeyword === '') {
            return $query;
        }

        $searchableColumns = array_values(array_filter(
            ['Account_Name', 'Issue', 'TO_Account', 'SK_Code'],
            static::tableHasColumn(...)
        ));

        if ($searchableColumns === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($normalizedKeyword, $searchableColumns) {
            foreach ($searchableColumns as $column) {
                $q->orWhereRaw(
                    "REPLACE({$column}, ' ', '') like ?",
                    ["%{$normalizedKeyword}%"]
                );
            }
        });
    }

    // ════════════════════════════════════════════════════════════════
    // 편의 메서드 (자주 쓰는 기능을 짧게 호출하기 위해)
    // ════════════════════════════════════════════════════════════════

    /**
     * PRD 4.3 "완료처리" 토글 스위치 동작
     * ─────────────────────────────────
     * true  → Status·CompletedDate를 함께 완료로 맞춤
     * false → Status·CompletedDate를 함께 진행중으로 맞춤
     *
     * 사용 예:
     *   $record->toggleComplete(true)   // 완료 처리
     *   $record->toggleComplete(false)  // 완료 취소
     */
    public function toggleComplete(bool $done): void
    {
        if (! static::tableHasColumn('CompletedDate') && ! static::tableHasColumn('Status')) {
            return;
        }

        $this->applyCompletionState($done);
        $this->save();
    }

    public function applyCompletionState(bool $done): void
    {
        foreach (static::completionAttributes($done, $this->CompletedDate) as $column => $value) {
            $this->setAttribute($column, $value);
        }
    }
}
