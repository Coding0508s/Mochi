<?php

namespace App\Models;

use App\Casts\LegacyDateTimeCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $ID
 * @property int|null $TearcherID Legacy FK to Teachers.ID (column name typo in DB)
 * @property string|null $Name
 * @property string|null $SK_Code
 * @property string|null $Account_Name
 * @property string|null $TR_Name
 * @property Carbon|null $RetirementDate
 * @property bool|null $RecommendYN
 * @property string|null $RecommendDescription
 * @property string|null $Description
 * @property string|null $Status
 */
class RetirementList extends Model
{
    protected $table = 'S_RetirementList';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'TearcherID',
        'Name',
        'SK_Code',
        'Account_Name',
        'TR_Name',
        'RetirementDate',
        'RecommendYN',
        'RecommendDescription',
        'Description',
        'Status',
        'FGC_Creator',
        'FGC_CreateDate',
        'FGC_LastModifier',
        'FGC_LastModifyDate',
    ];

    protected function casts(): array
    {
        return [
            'RetirementDate' => LegacyDateTimeCast::class,
            'FGC_CreateDate' => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
            'RecommendYN' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'TearcherID', 'ID');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'SK_Code', 'SKcode');
    }

    public function displayAccountName(): string
    {
        $fromInstitution = trim($this->institution?->resolvedAccountName() ?? '');
        if ($fromInstitution !== '') {
            return $fromInstitution;
        }

        return trim((string) ($this->Account_Name ?? ''));
    }

    public function displayPosition(): ?string
    {
        $position = trim((string) ($this->teacher?->Position ?? ''));

        return $position !== '' ? $position : null;
    }

    public function displayPhone(): ?string
    {
        $fromTeacher = trim((string) ($this->teacher?->Phone ?? ''));
        if ($fromTeacher !== '') {
            return $fromTeacher;
        }

        $phoneColumn = config('coach_retired_teachers.teacher_master.columns.phone', 'Phone');
        $master = $this->teacher?->masterRecord;
        if ($master instanceof TeacherMasterDb) {
            $fromMaster = trim((string) ($master->getAttribute($phoneColumn) ?? ''));
            if ($fromMaster !== '') {
                return $fromMaster;
            }
        }

        return null;
    }

    /**
     * @param  Builder<RetirementList>  $query
     */
    public function scopeForYear(Builder $query, int $year): void
    {
        $column = config('coach_retired_teachers.columns.retirement_date', 'RetirementDate');
        $query->whereYear($column, $year);
    }

    /**
     * 현재 퇴직 중인 교사만 (복직 처리된 이력·연결 교사 활성 상태는 제외).
     *
     * @param  Builder<RetirementList>  $query
     */
    public function scopeCurrentlyRetired(Builder $query): void
    {
        $statusColumn = config('coach_retired_teachers.columns.status', 'Status');
        $retiredStatus = config('coach_retired_teachers.statuses.retired', '퇴직');

        $query->where(function (Builder $q) use ($statusColumn, $retiredStatus): void {
            $q->whereHas('teacher', function (Builder $teacherQuery): void {
                $teacherQuery->where('Status', '퇴직');
            })->orWhere(function (Builder $orphan) use ($statusColumn, $retiredStatus): void {
                $orphan->where($statusColumn, $retiredStatus)
                    ->whereDoesntHave('teacher');
            });
        });
    }
}
