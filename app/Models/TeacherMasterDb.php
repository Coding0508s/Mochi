<?php

namespace App\Models;

use App\Casts\LegacyDateTimeCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class TeacherMasterDb extends Model
{
    protected $table = 'S_TeacherMasterDB';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        $columns = config('coach_retired_teachers.teacher_master.columns', []);
        $casts = [];

        foreach ([$columns['retired_at'] ?? null, $columns['gs_essentials'] ?? null, $columns['ls_essentials'] ?? null] as $column) {
            if (is_string($column) && $column !== '') {
                $casts[$column] = LegacyDateTimeCast::class;
            }
        }

        return $casts;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, $this->teacherIdColumn(), 'ID');
    }

    public function retirementList(): HasOne
    {
        $retirementTeacherColumn = config('coach_retired_teachers.columns.teacher_id', 'TearcherID');

        return $this->hasOne(RetirementList::class, $retirementTeacherColumn, $this->teacherIdColumn());
    }

    public function institution(): BelongsTo
    {
        $skCodeColumn = $this->skCodeColumn();

        return $this->belongsTo(Institution::class, $skCodeColumn, 'SKcode');
    }

    /**
     * @param  Builder<TeacherMasterDb>  $query
     */
    public function scopeRetired(Builder $query): void
    {
        $statusColumn = $this->statusColumn();
        if (! Schema::hasColumn($this->getTable(), $statusColumn)) {
            return;
        }

        $query->where($statusColumn, config('coach_retired_teachers.statuses.retired', '퇴직'));
    }

    /**
     * @param  Builder<TeacherMasterDb>  $query
     */
    public function scopeForYear(Builder $query, int $year): void
    {
        $retiredAtColumn = $this->retiredAtColumn();
        if (! Schema::hasColumn($this->getTable(), $retiredAtColumn)) {
            return;
        }

        $query->whereYear($retiredAtColumn, $year);
    }

    public function displayAccountName(): string
    {
        $fromInstitution = trim($this->institution?->resolvedAccountName() ?? '');
        if ($fromInstitution !== '') {
            return $fromInstitution;
        }

        foreach ($this->accountNameColumns() as $column) {
            $fromMaster = trim((string) ($this->getAttribute($column) ?? ''));
            if ($fromMaster !== '') {
                return $fromMaster;
            }
        }

        return trim((string) ($this->teacher?->School_Name ?? ''));
    }

    public function displayRecommendYn(): bool
    {
        if ($this->relationLoaded('retirementList')) {
            return (bool) ($this->retirementList?->RecommendYN);
        }

        $teacherId = $this->resolveTeacherId();
        if ($teacherId === null) {
            return false;
        }

        return (bool) RetirementList::query()
            ->where(config('coach_retired_teachers.columns.teacher_id', 'TearcherID'), $teacherId)
            ->value('RecommendYN');
    }

    public function displayPosition(): ?string
    {
        $position = trim((string) ($this->teacher?->Position ?? ''));

        return $position !== '' ? $position : null;
    }

    public function resolveTeacherId(): ?int
    {
        foreach ($this->teacherIdColumns() as $column) {
            $teacherId = $this->getAttribute($column);
            if (is_numeric($teacherId) && (int) $teacherId > 0) {
                return (int) $teacherId;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function teacherIdColumns(): array
    {
        $table = $this->getTable();
        $configured = config('coach_retired_teachers.teacher_master.columns.teacher_id', 'TearcherID');
        $candidates = array_unique([$configured, 'TearcherID', 'TeacherID']);

        return array_values(array_filter(
            $candidates,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }

    public function teacherIdColumn(): string
    {
        $columns = $this->teacherIdColumns();

        return $columns[0] ?? config('coach_retired_teachers.teacher_master.columns.teacher_id', 'TearcherID');
    }

    /**
     * @return list<string>
     */
    public function accountNameColumns(): array
    {
        $table = $this->getTable();
        $configured = config('coach_retired_teachers.teacher_master.columns.school_name', 'Account_Name');
        $candidates = array_unique([$configured, 'Account_Name', 'School_Name']);

        return array_values(array_filter(
            $candidates,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }

    public function skCodeColumn(): string
    {
        return config('coach_retired_teachers.teacher_master.columns.sk_code', 'SK_Code');
    }

    public function retiredAtColumn(): string
    {
        return config('coach_retired_teachers.teacher_master.columns.retired_at', 'RetirementDate');
    }

    public function statusColumn(): string
    {
        return config('coach_retired_teachers.teacher_master.columns.status', 'Status');
    }

    public function schoolNameColumn(): string
    {
        $columns = $this->accountNameColumns();

        return $columns[0] ?? config('coach_retired_teachers.teacher_master.columns.school_name', 'Account_Name');
    }

    public static function findByTeacherId(int $teacherId): ?self
    {
        $model = new self;

        foreach ($model->teacherIdColumns() as $column) {
            $record = self::query()->where($column, $teacherId)->first();
            if ($record instanceof self) {
                return $record;
            }
        }

        return null;
    }
}
