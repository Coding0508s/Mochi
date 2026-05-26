<?php

namespace App\Models;

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
    ];

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
     */
    public static function yearSourceColumn(): ?string
    {
        foreach (['Year', 'Support_Date', 'CreatedDate', 'FGC_CreateDate'] as $column) {
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
     * 연도 필터 드롭다운용 distinct 연도 목록.
     *
     * @return Collection<int, int>
     */
    public static function distinctFilterYears(): Collection
    {
        $column = static::yearSourceColumn();

        if ($column === null) {
            return collect();
        }

        if ($column === 'Year') {
            return static::query()
                ->whereNotNull('Year')
                ->distinct()
                ->orderByDesc('Year')
                ->pluck('Year')
                ->map(fn (mixed $year): int => (int) $year)
                ->values();
        }

        $yearExpression = static::yearExpressionForColumn($column);

        return static::query()
            ->whereNotNull($column)
            ->selectRaw("{$yearExpression} as filter_year")
            ->distinct()
            ->orderByDesc('filter_year')
            ->pluck('filter_year')
            ->map(fn (mixed $year): int => (int) $year)
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

    public function scopeOfYear(Builder $query, ?int $year): Builder
    {
        if (blank($year)) {
            return $query;
        }

        $column = static::yearSourceColumn();

        if ($column === null) {
            return $query;
        }

        if ($column === 'Year') {
            return $query->where('Year', $year);
        }

        return $query->whereNotNull($column)
            ->whereYear($column, $year);
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
        if (! static::tableHasColumn('CompletedDate')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereNotNull('CompletedDate');
    }

    /**
     * 아직 완료되지 않은 지원 내역 (진행 중)
     *
     * 사용 예:
     *   SupportRecord::inProgress()->get()
     */
    public function scopeInProgress(Builder $query): Builder
    {
        if (! static::tableHasColumn('CompletedDate')) {
            return $query;
        }

        return $query->whereNull('CompletedDate');
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
     * true  → CompletedDate에 지금 시각 기록 (완료)
     * false → CompletedDate를 null로 초기화 (완료 취소)
     *
     * 사용 예:
     *   $record->toggleComplete(true)   // 완료 처리
     *   $record->toggleComplete(false)  // 완료 취소
     */
    public function toggleComplete(bool $done): void
    {
        if (! static::tableHasColumn('CompletedDate')) {
            return;
        }

        $this->CompletedDate = $done ? now() : null;
        $this->save();
    }
}
