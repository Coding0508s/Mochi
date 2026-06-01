<?php

namespace App\Livewire;

use App\Actions\ReinstateTeacher;
use App\Models\RetirementList;
use App\Models\Teacher;
use App\Models\TeacherMasterDb;
use App\Models\User;
use App\Support\CoachRetirementListScope;
use App\Support\CoachTeacherScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CoachRetiredTeacherList extends Component
{
    use WithPagination;

    /** 빈 문자열이면 전체 연도 */
    public string $filterYear = '';

    public string $search = '';

    public bool $showDetailModal = false;

    public ?array $selectedRetirement = null;

    public bool $showReinstateModal = false;

    public string $reinstateTargetName = '';

    public string $reinstateClassParticipation = 'in';

    public function mount(): void
    {
        // 구 Mochi 목록은 연도 필터 없음 — 기본은 전체 연도
        $this->filterYear = '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYear(): void
    {
        if ($this->filterYear === '') {
            return;
        }

        $maxYear = now()->year;
        $minYear = $maxYear - 10;
        $year = (int) $this->filterYear;

        $this->filterYear = (string) max($minYear, min($maxYear, $year));
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterYear = '';
        $this->resetPage();
    }

    public function openDetailModal(int $retirementId): void
    {
        if (! Schema::hasTable($this->listTable())) {
            return;
        }

        if ($this->usingTeacherMaster()) {
            if ($this->listsFromTeachersStatus()) {
                $teacher = $this->buildBaseQuery()
                    ->with(['institution.accountInfo', 'masterRecord', 'retirementList'])
                    ->where('ID', $retirementId)
                    ->first();

                if (! $teacher instanceof Teacher) {
                    return;
                }

                $this->selectedRetirement = $this->selectedRetirementFromTeacher($teacher);
                $this->showDetailModal = true;

                return;
            }

            $record = $this->buildBaseQuery()
                ->with(['teacher', 'institution.accountInfo', 'retirementList'])
                ->where(config('coach_retired_teachers.teacher_master.columns.id', 'ID'), $retirementId)
                ->first();

            if (! $record instanceof TeacherMasterDb) {
                return;
            }

            $teacherId = $record->resolveTeacherId();
            $recommend = $this->recommendationForTeacher($teacherId);
            $retiredAtColumn = config('coach_retired_teachers.teacher_master.columns.retired_at', 'RetirementDate');
            $nameColumn = config('coach_retired_teachers.teacher_master.columns.name', 'Name');
            $skCodeColumn = config('coach_retired_teachers.teacher_master.columns.sk_code', 'SK_Code');
            $statusColumn = config('coach_retired_teachers.teacher_master.columns.status', 'Status');
            $trNameColumn = config('coach_retired_teachers.teacher_master.columns.tr_name', 'TR_Name');
            $descriptionColumn = config('coach_retired_teachers.teacher_master.columns.description', 'Description');

            $this->selectedRetirement = [
                'id' => $record->getKey(),
                'teacher_id' => $teacherId,
                'name' => (string) ($record->getAttribute($nameColumn) ?? ''),
                'sk_code' => (string) ($record->getAttribute($skCodeColumn) ?? ''),
                'account_name' => $record->displayAccountName(),
                'position' => $record->displayPosition(),
                'tr_name' => (string) (($record->getAttribute($trNameColumn) ?? '') ?: ($record->institution?->accountInfo?->TR ?? '')),
                'retirement_date' => $record->getAttribute($retiredAtColumn)?->format('Y-m-d') ?? null,
                'recommend_yn' => (bool) $recommend['recommend_yn'],
                'recommend_description' => $recommend['recommend_description'],
                'description' => (string) (($record->getAttribute($descriptionColumn) ?? '') ?: ($recommend['description'] ?? '')),
                'status' => (string) ($record->getAttribute($statusColumn) ?? ''),
                'can_reinstate' => $this->canReinstateTeacher($teacherId),
            ];
            $this->showDetailModal = true;

            return;
        }

        $record = $this->buildBaseQuery()
            ->with(['teacher', 'institution.accountInfo'])
            ->where('ID', $retirementId)
            ->first();

        if (! $record) {
            return;
        }

        $teacherId = (int) ($record->TearcherID ?? 0);

        $this->selectedRetirement = [
            'id' => $record->ID,
            'teacher_id' => $record->TearcherID,
            'name' => $record->Name,
            'sk_code' => $record->SK_Code,
            'account_name' => $record->displayAccountName(),
            'position' => $record->displayPosition(),
            'tr_name' => $record->TR_Name,
            'retirement_date' => $record->RetirementDate?->format('Y-m-d'),
            'recommend_yn' => (bool) $record->RecommendYN,
            'recommend_description' => $record->RecommendDescription,
            'description' => $record->Description,
            'status' => $record->Status,
            'can_reinstate' => $this->canReinstateTeacher($teacherId > 0 ? $teacherId : null),
        ];
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedRetirement = null;
    }

    public function openReinstateModal(): void
    {
        $teacherId = (int) ($this->selectedRetirement['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return;
        }

        $teacher = Teacher::query()->find($teacherId);
        if (! $teacher || ! $teacher->isRetired()) {
            session()->flash('warning', '퇴직 상태인 교사만 복직 처리할 수 있습니다.');

            return;
        }

        $this->reinstateTargetName = (string) ($teacher->Name ?? '교사');
        $this->reinstateClassParticipation = $teacher->ClassInOut ? 'in' : 'out';
        $this->resetReinstateValidation();
        $this->showReinstateModal = true;
    }

    public function closeReinstateModal(): void
    {
        $this->showReinstateModal = false;
        $this->reinstateTargetName = '';
        $this->reinstateClassParticipation = 'in';
        $this->resetReinstateValidation();
    }

    public function reinstate(): void
    {
        $teacherId = (int) ($this->selectedRetirement['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return;
        }

        $this->validate([
            'reinstateClassParticipation' => ['required', Rule::in(['in', 'out'])],
        ], [
            'reinstateClassParticipation.required' => '수업 참여 여부를 선택해 주세요.',
            'reinstateClassParticipation.in' => '수업 참여 여부를 선택해 주세요.',
        ]);

        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $classInOut = $this->reinstateClassParticipation === 'in';

        try {
            app(ReinstateTeacher::class)->execute($teacherId, $user, $classInOut);
            session()->flash('success', '교사가 복직 처리되었습니다. 교사 지원·연락처 목록에 다시 표시됩니다.');
            $this->closeReinstateModal();
            $this->closeDetailModal();
            $this->resetPage();
        } catch (AuthorizationException) {
            session()->flash(
                'warning',
                '이 교사의 복직 처리 권한이 없습니다. 담당 TR 기관 교사는 권한이 있는 계정으로 처리해 주세요.',
            );
        } catch (\InvalidArgumentException $exception) {
            session()->flash('warning', $exception->getMessage());
        }
    }

    public function canReinstateTeacher(?int $teacherId): bool
    {
        if ($teacherId === null || $teacherId <= 0) {
            return false;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        $teacher = Teacher::query()->find($teacherId);
        if (! $teacher || ! $teacher->isRetired()) {
            return false;
        }

        if ($user->hasPlatformWideViewAccess()) {
            return true;
        }

        $scopedQuery = Teacher::query()->where('ID', $teacherId);
        CoachTeacherScope::apply($scopedQuery, $user);

        return $scopedQuery->exists();
    }

    private function resetReinstateValidation(): void
    {
        $this->resetValidation(['reinstateClassParticipation']);
    }

    public function render()
    {
        if (! Schema::hasTable($this->listTable())) {
            return view('livewire.coach-retired-teacher-list', [
                'retirements' => collect(),
                'tableMissing' => true,
            ]);
        }

        $eagerLoad = $this->listsFromTeachersStatus()
            ? ['institution.accountInfo', 'masterRecord', 'retirementList']
            : ['teacher', 'institution.accountInfo', 'retirementList'];

        $retirements = $this->buildBaseQuery()
            ->with($eagerLoad)
            ->tap(fn (Builder $query) => $this->applyYearFilter($query))
            ->tap(fn (Builder $query) => $this->applySearchFilter($query))
            ->when($this->listsFromTeachersStatus(), function (Builder $query): void {
                $this->applyTeacherStatusRetirementDateOrder($query);
            })
            ->when($this->usingTeacherMaster() && ! $this->listsFromTeachersStatus(), function (Builder $query): void {
                $retiredAtColumn = config('coach_retired_teachers.teacher_master.columns.retired_at', 'RetirementDate');
                $nameColumn = config('coach_retired_teachers.teacher_master.columns.name', 'Name');
                $table = $query->getModel()->getTable();

                if (Schema::hasColumn($table, $retiredAtColumn)) {
                    $query->orderByDesc($retiredAtColumn);
                }
                if (Schema::hasColumn($table, $nameColumn)) {
                    $query->orderBy($nameColumn);
                }
            })
            ->when(! $this->usingTeacherMaster(), function (Builder $query): void {
                $query->orderByDesc(config('coach_retired_teachers.columns.retirement_date', 'RetirementDate'))
                    ->orderBy('Name');
            })
            ->paginate(50);

        return view('livewire.coach-retired-teacher-list', [
            'retirements' => $retirements,
            'tableMissing' => false,
        ]);
    }

    /**
     * @return Builder<Model>
     */
    private function buildBaseQuery(): Builder
    {
        if ($this->usingTeacherMaster()) {
            if ($this->listsFromTeachersStatus()) {
                $query = Teacher::query();
                CoachRetirementListScope::apply($query);
                $query->retired();

                return $query;
            }

            $query = TeacherMasterDb::query();
            CoachRetirementListScope::apply($query);
            $query->retired();

            return $query;
        }

        $query = RetirementList::query();
        CoachRetirementListScope::apply($query);
        $query->currentlyRetired();

        return $query;
    }

    /**
     * Teachers.Status 기준 목록을 퇴직 처리일(최신순)로 정렬합니다.
     *
     * 퇴직일은 Teachers 테이블에 없고 S_TeacherMasterDB(우선) 또는
     * S_RetirementList에 있으므로 상관 서브쿼리 + COALESCE로 정렬합니다.
     *
     * @param  Builder<Model>  $query
     */
    private function applyTeacherStatusRetirementDateOrder(Builder $query): void
    {
        $teacherTable = (new Teacher)->getTable();

        $masterModel = new TeacherMasterDb;
        $masterTable = $masterModel->getTable();
        $masterRetiredAt = config('coach_retired_teachers.teacher_master.columns.retired_at', 'RetirementDate');

        $retirementTable = (new RetirementList)->getTable();
        $retirementTeacherId = config('coach_retired_teachers.columns.teacher_id', 'TearcherID');
        $retirementRetiredAt = config('coach_retired_teachers.columns.retirement_date', 'RetirementDate');

        $dateExpressions = [];

        if (Schema::hasTable($masterTable) && Schema::hasColumn($masterTable, $masterRetiredAt)) {
            $masterTeacherId = $masterModel->teacherIdColumn();

            if (Schema::hasColumn($masterTable, $masterTeacherId)) {
                $dateExpressions[] = "(SELECT {$masterRetiredAt} FROM {$masterTable} "
                    ."WHERE {$masterTable}.{$masterTeacherId} = {$teacherTable}.ID "
                    ."ORDER BY {$masterRetiredAt} DESC LIMIT 1)";
            }
        }

        if (Schema::hasTable($retirementTable)
            && Schema::hasColumn($retirementTable, $retirementRetiredAt)
            && Schema::hasColumn($retirementTable, $retirementTeacherId)) {
            $dateExpressions[] = "(SELECT {$retirementRetiredAt} FROM {$retirementTable} "
                ."WHERE {$retirementTable}.{$retirementTeacherId} = {$teacherTable}.ID "
                ."ORDER BY {$retirementRetiredAt} DESC LIMIT 1)";
        }

        if ($dateExpressions !== []) {
            $retirementDateSql = count($dateExpressions) === 1
                ? $dateExpressions[0]
                : 'COALESCE('.implode(', ', $dateExpressions).')';

            $query->orderByRaw("{$retirementDateSql} DESC");
        }

        $query->orderByDesc('ID');
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyYearFilter(Builder $query): void
    {
        $year = $this->resolvedFilterYear();
        if ($year === null) {
            return;
        }

        if ($this->listsFromTeachersStatus()) {
            $query->where(function (Builder $yearQuery) use ($year): void {
                $yearQuery->whereHas('masterRecord', function (Builder $masterQuery) use ($year): void {
                    $masterQuery->forYear($year);
                })->orWhereHas('retirementList', function (Builder $listQuery) use ($year): void {
                    $listQuery->forYear($year);
                });
            });

            return;
        }

        if ($this->usingTeacherMaster()) {
            if (method_exists($query->getModel(), 'scopeForYear')) {
                $query->forYear($year);
            }

            return;
        }

        $query->forYear($year);
    }

    private function resolvedFilterYear(): ?int
    {
        if ($this->filterYear === '') {
            return null;
        }

        return (int) $this->filterYear;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySearchFilter(Builder $query): void
    {
        if (! filled($this->search)) {
            return;
        }

        $term = '%'.preg_replace('/\s+/u', '', $this->search).'%';

        if ($this->listsFromTeachersStatus()) {
            $query->where(function (Builder $q) use ($term): void {
                $q->whereRaw("REPLACE(Name, ' ', '') LIKE ?", [$term])
                    ->orWhereRaw("REPLACE(School_Name, ' ', '') LIKE ?", [$term])
                    ->orWhere('SK_Code', 'LIKE', $term);
            });

            return;
        }

        if ($this->usingTeacherMaster()) {
            $nameColumn = config('coach_retired_teachers.teacher_master.columns.name', 'Name');
            $skCodeColumn = config('coach_retired_teachers.teacher_master.columns.sk_code', 'SK_Code');
            $table = $query->getModel()->getTable();
            /** @var TeacherMasterDb $masterModel */
            $masterModel = $query->getModel();
            $accountNameColumns = $masterModel->accountNameColumns();

            $query->where(function (Builder $q) use ($term, $table, $nameColumn, $skCodeColumn, $accountNameColumns): void {
                $hasAnyCondition = false;
                if (Schema::hasColumn($table, $nameColumn)) {
                    $q->whereRaw("REPLACE({$nameColumn}, ' ', '') LIKE ?", [$term]);
                    $hasAnyCondition = true;
                }
                foreach ($accountNameColumns as $schoolColumn) {
                    if ($hasAnyCondition) {
                        $q->orWhereRaw("REPLACE({$schoolColumn}, ' ', '') LIKE ?", [$term]);
                    } else {
                        $q->whereRaw("REPLACE({$schoolColumn}, ' ', '') LIKE ?", [$term]);
                        $hasAnyCondition = true;
                    }
                }
                if (Schema::hasColumn($table, $skCodeColumn)) {
                    if ($hasAnyCondition) {
                        $q->orWhere($skCodeColumn, 'LIKE', $term);
                    } else {
                        $q->where($skCodeColumn, 'LIKE', $term);
                    }
                }
            });

            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->whereRaw("REPLACE(Name, ' ', '') LIKE ?", [$term])
                ->orWhereRaw("REPLACE(Account_Name, ' ', '') LIKE ?", [$term])
                ->orWhere('SK_Code', 'LIKE', $term);
        });
    }

    private function usingTeacherMaster(): bool
    {
        return config('coach_retired_teachers.list_source', 'retirement_list') === 'teacher_master';
    }

    private function listTable(): string
    {
        if ($this->usingTeacherMaster()) {
            if ($this->listsFromTeachersStatus()) {
                return (new Teacher)->getTable();
            }

            return config('coach_retired_teachers.teacher_master.table', 'S_TeacherMasterDB');
        }

        return 'S_RetirementList';
    }

    private function listsFromTeachersStatus(): bool
    {
        return $this->usingTeacherMaster()
            && (bool) config('coach_retired_teachers.teacher_master.list_from_teachers_status', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedRetirementFromTeacher(Teacher $teacher): array
    {
        $recommend = $this->recommendationForTeacher($teacher->ID);
        $master = $teacher->masterRecord;
        $descriptionColumn = config('coach_retired_teachers.teacher_master.columns.description', 'Description');

        return [
            'id' => $teacher->ID,
            'teacher_id' => $teacher->ID,
            'name' => (string) ($teacher->Name ?? ''),
            'sk_code' => (string) ($teacher->SK_Code ?? ''),
            'account_name' => $teacher->displayAccountName(),
            'position' => $teacher->displayPosition(),
            'tr_name' => $teacher->listTrName(),
            'retirement_date' => $teacher->listRetirementDate()?->format('Y-m-d'),
            'recommend_yn' => (bool) $recommend['recommend_yn'],
            'recommend_description' => $recommend['recommend_description'],
            'description' => (string) (($master?->getAttribute($descriptionColumn) ?? '') ?: ($recommend['description'] ?? '')),
            'status' => (string) ($teacher->Status ?? ''),
            'can_reinstate' => $this->canReinstateTeacher($teacher->ID),
        ];
    }

    /**
     * @return array{recommend_yn: bool, recommend_description: ?string, description: ?string}
     */
    private function recommendationForTeacher(?int $teacherId): array
    {
        if ($teacherId === null || $teacherId <= 0 || ! Schema::hasTable('S_RetirementList')) {
            return [
                'recommend_yn' => false,
                'recommend_description' => null,
                'description' => null,
            ];
        }

        $record = RetirementList::query()
            ->where(config('coach_retired_teachers.columns.teacher_id', 'TearcherID'), $teacherId)
            ->first();

        if (! $record) {
            return [
                'recommend_yn' => false,
                'recommend_description' => null,
                'description' => null,
            ];
        }

        return [
            'recommend_yn' => (bool) $record->RecommendYN,
            'recommend_description' => $record->RecommendDescription,
            'description' => $record->Description,
        ];
    }
}
