<?php

namespace App\Models;

use App\Casts\LegacyDateTimeCast;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * ═══════════════════════════════════════════════════════════════
 * [교사/연락처] Teacher 모델
 * ───────────────────────────────────────────────────────────────
 * 데이터베이스 테이블: Teachers
 *
 * PRD 4.2 '기관 연락처 정보 및 계정 관리' 화면에서 보여주는 데이터입니다.
 *
 * 사용 예시:
 *   // 이름으로 검색
 *   Teacher::searchBy('name', '김유진')->get()
 *
 *   // 이 교사가 속한 기관 정보 가져오기
 *   $teacher->institution->AccountName
 *
 *   // 수업에 참여 중인 교사만
 *   Teacher::active()->get()
 * ═══════════════════════════════════════════════════════════════
 *
 * @property int $ID
 * @property string $SK_Code 소속 기관 코드 (Institution.SKcode 연결)
 * @property string $Name 교사 이름
 * @property string $Email 이메일
 * @property string $Phone 연락처
 * @property string $Position 직급
 * @property string $Status 상태 (재직/퇴직 등)
 * @property string $CO_Name 담당 CO 이름
 */
class Teacher extends Model
{
    // ─── 테이블 설정 ──────────────────────────────────────────────────
    protected $table = 'Teachers';
    // 원본 SQL의 테이블 이름 그대로 사용합니다.

    protected $primaryKey = 'ID';

    public $timestamps = false;

    // ─── 대량 입력 허용 필드 ──────────────────────────────────────────
    protected $fillable = [
        'School_Name',
        'SK_Code',
        'CO_ID',
        'CO_Name',
        'Name',
        'Email',
        'Phone',
        'Position',
        'Description',
        'Status',
        'ClassInOut',
        'NewSenior',
        'CS',
        'CO',
        'Created_Date',
        'GrapeSEEDEssentials',
        'LittleSEEDEssentials',
        'Plan_1st_Support_Date',
        'Plan_2nd_Support_Date',
        'Plan_3rd_Support_Date',
        'Plan_4th_Support_Date',
        'Plan_1st_Support_Type',
        'Plan_2nd_Support_Type',
        'Plan_3rd_Support_Type',
        'Plan_4th_Support_Type',
        '_1st_Support_Date',
        '_2nd_Support_Date',
        '_3rd_Support_Date',
        '_4th_Support_Date',
        '_1st_Support_Type',
        '_2nd_Support_Type',
        '_3rd_Support_Type',
        '_4th_Support_Type',
        'Unit_21_',
        'Unit_31_',
        'GrapeSEED_Connect_Training',
        'Nexus_Training',
        'Certi_Delivery',
        'Certi_Delivery_LS',
        'LittleSEED_Support',
        'LittleSEED_Pro_Tips_',
        'LittleSEED_Release_Note',
    ];

    // ─── 날짜/타입 자동 변환 ──────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'GrapeSEEDEssentials' => LegacyDateTimeCast::class,
            'LittleSEEDEssentials' => LegacyDateTimeCast::class,
            'Plan_1st_Support_Date' => LegacyDateTimeCast::class,
            'Plan_2nd_Support_Date' => LegacyDateTimeCast::class,
            'Plan_3rd_Support_Date' => LegacyDateTimeCast::class,
            'Plan_4th_Support_Date' => LegacyDateTimeCast::class,
            'Unit_21_' => 'datetime',
            '_1st_Support_Date' => LegacyDateTimeCast::class,
            '_2nd_Support_Date' => LegacyDateTimeCast::class,
            '_3rd_Support_Date' => LegacyDateTimeCast::class,
            '_4th_Support_Date' => LegacyDateTimeCast::class,
            'Unit_31_' => 'datetime',
            'LittleSEED_Pro_Tips_' => 'datetime',
            'GrapeSEED_Connect_Training' => 'datetime',
            'Nexus_Training' => 'datetime',
            'LittleSEED_Support' => 'datetime',
            'LittleSEED_Release_Note' => 'datetime',
            'Created_Date' => 'datetime',
            'FGC_CreateDate' => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
            'FGC_Rowversion' => 'datetime',

            'Certi_Delivery_LS' => 'boolean',
            'Certi_Delivery' => 'boolean',
            'ClassInOut' => 'boolean',
            'NewSenior' => 'boolean',
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // 관계(Relationship) 정의
    // ════════════════════════════════════════════════════════════════

    /**
     * 이 교사가 소속된 기관
     *
     * 사용 예:
     *   $teacher->institution          // 기관 객체
     *   $teacher->institution->AccountName  // 기관명
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'SK_Code', 'SKcode');
        // "S_AccountName 테이블에서 SKcode = 이 교사의 SK_Code 인 기관을 찾아"
    }

    public function masterRecord(): HasOne
    {
        $foreignKey = (new TeacherMasterDb)->teacherIdColumn();

        return $this->hasOne(TeacherMasterDb::class, $foreignKey, 'ID');
    }

    public function retirementList(): HasOne
    {
        $foreignKey = config('coach_retired_teachers.columns.teacher_id', 'TearcherID');

        return $this->hasOne(RetirementList::class, $foreignKey, 'ID');
    }

    public function displayAccountName(): string
    {
        $fromInstitution = trim($this->institution?->resolvedAccountName() ?? '');
        if ($fromInstitution !== '') {
            return $fromInstitution;
        }

        $fromMaster = trim((string) ($this->masterRecord?->displayAccountName() ?? ''));
        if ($fromMaster !== '') {
            return $fromMaster;
        }

        return trim((string) ($this->School_Name ?? ''));
    }

    public function displayPosition(): ?string
    {
        $position = trim((string) ($this->Position ?? ''));

        return $position !== '' ? $position : null;
    }

    public function displayRecommendYn(): bool
    {
        if ($this->relationLoaded('retirementList')) {
            return (bool) ($this->retirementList?->RecommendYN);
        }

        return (bool) ($this->retirementList()->value('RecommendYN'));
    }

    public function listTrName(): string
    {
        $fromMaster = trim((string) ($this->masterRecord?->getAttribute(
            config('coach_retired_teachers.teacher_master.columns.tr_name', 'TR_Name')
        ) ?? ''));
        if ($fromMaster !== '') {
            return $fromMaster;
        }

        return trim((string) ($this->institution?->accountInfo?->TR ?? ''));
    }

    public function listRetirementDate(): ?Carbon
    {
        $masterDate = $this->normalizeLegacyDate(
            $this->masterRecord?->getAttribute(
                config('coach_retired_teachers.teacher_master.columns.retired_at', 'RetirementDate')
            )
        );
        if ($masterDate !== null) {
            return $masterDate;
        }

        return $this->normalizeLegacyDate($this->retirementList?->RetirementDate);
    }

    private function normalizeLegacyDate(mixed $value): ?Carbon
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::instance($value);
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    public function scopeRetired(Builder $query): Builder
    {
        return $query->where('Status', config('coach_retired_teachers.statuses.retired', '퇴직'));
    }

    public function getRetirementDateAttribute(): ?Carbon
    {
        return $this->listRetirementDate();
    }

    public function getTRNameAttribute(): string
    {
        return $this->listTrName();
    }

    // ════════════════════════════════════════════════════════════════
    // 검색용 스코프 (PRD 4.2 라디오 버튼 검색 기능용)
    // ════════════════════════════════════════════════════════════════

    /**
     * PRD 4.2 라디오 버튼에 대응하는 통합 검색
     *
     * $type 에 따라 검색 대상 컬럼이 달라집니다:
     *   'email'  → Email 컬럼에서 검색
     *   'name'   → Name 컬럼에서 검색
     *   'phone'  → Phone 컬럼에서 검색
     *   'school' → School_Name 컬럼에서 검색
     *
     * 사용 예:
     *   Teacher::searchBy('email', 'kim@example.com')->get()
     *   Teacher::searchBy('name', '김유진')->get()
     */
    public function scopeSearchBy(Builder $query, string $type, ?string $keyword): Builder
    {
        if (blank($keyword)) {
            return $query;
        }

        $normalizedKeyword = preg_replace('/\s+/u', '', (string) $keyword) ?? '';
        if ($normalizedKeyword === '') {
            return $query;
        }

        $columnMap = [
            'email' => 'Email',
            'name' => 'Name',
            'phone' => 'Phone',
            'school' => 'School_Name',
        ];

        $column = $columnMap[$type] ?? 'Name';
        // 지정된 타입이 없으면 기본값으로 이름 컬럼에서 검색합니다.

        return $query->whereRaw("REPLACE({$column}, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
    }

    /**
     * 수업에 참여 중인 교사만 조회
     *
     * 사용 예:
     *   Teacher::active()->get()
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ClassInOut', true);
        // ClassInOut 이 true(= 1)이면 수업 참여 중입니다.
    }

    public function isRetired(): bool
    {
        return trim((string) $this->Status) === '퇴직';
    }

    public function scopeExcludeRetired(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('Status')
                ->orWhere('Status', '!=', '퇴직');
        });
    }

    /**
     * 특정 기관에 소속된 교사만 조회
     *
     * 사용 예:
     *   Teacher::ofInstitution('SK001')->get()
     */
    public function scopeOfInstitution(Builder $query, ?string $skCode): Builder
    {
        if (blank($skCode)) {
            return $query;
        }

        return $query->where('SK_Code', $skCode);
    }
}
