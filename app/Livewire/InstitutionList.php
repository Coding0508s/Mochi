<?php

namespace App\Livewire;

use App\Actions\UpdateInstitutionDetail;
use App\Actions\UpdateInstitutionManagers;
use App\DataTransferObjects\InstitutionListFilters;
use App\Livewire\Concerns\ManagesInstitutionSupportDetailEdit;
use App\Livewire\Concerns\OpensTeacherSupportHistoryDetail;
use App\Livewire\Concerns\PersistsInstitutionDetailForm;
use App\Livewire\Concerns\PersistsInstitutionManagerForm;
use App\Livewire\Concerns\ResolvesInstitutionFormPermissions;
use App\Models\AccountInformation;
use App\Models\AssignmentChangeRequest;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Support\InstitutionAccountListQuery;
use App\Support\InstitutionTeamSupportHistoryBuilder;
use App\Support\InstitutionUnifiedTimelineBuilder;
use App\Support\ManagerNameNormalizer;
use App\Support\SupportAuthorTeamResolver;
use App\Support\TeamMenuContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionList extends Component
{
    use ManagesInstitutionSupportDetailEdit;
    use OpensTeacherSupportHistoryDetail;
    use PersistsInstitutionDetailForm;
    use PersistsInstitutionManagerForm;
    use ResolvesInstitutionFormPermissions;

    // ─── 검색/필터 상태 (InstitutionFilter·InstitutionTable과 공유) ───
    public string $search = '';
    // 상단 검색창에 입력된 텍스트. 빈 문자열로 시작합니다.

    public string $statusFilter = 'all';
    // 기관 상태 필터: active | terminated | all (기본: S_Account_Information 전체 = phpMyAdmin 행 수와 동일)

    public string $assignmentFilter = '';
    // 담당자 배정 상태 필터: '' | assigned | unassigned | my_assigned

    public string $filterCo = '';

    public string $filterTr = '';

    public string $filterCs = '';

    public string $sortField = 'FGC_CreateDate';
    // 현재 정렬 기준 컬럼 (기본: S_Account_Information.FGC_CreateDate)

    public string $sortDirection = 'asc';
    // 정렬 방향: asc(오름차순) / desc(내림차순)

    public int $institutionTableTotal = 0;

    // ─── 상세 모달 상태 ───────────────────────────────────────────────
    public bool $showDetailModal = false;

    public ?array $selectedInstitution = null;

    public string $activeSupportTeamTab = SupportAuthorTeamResolver::TEAM_CO;

    /** @var array<string, mixed> */
    public array $teamSupportHistory = [];

    public bool $showUnknownSupportSection = false;

    public string $activeDetailTab = 'overview';

    public string $timelineTypeFilter = 'all';

    public string $timelineRangeFilter = '6m';

    public string $timelineAuthorFilter = '';

    public int $timelineVisibleCount = 30;

    /** @var list<array<string, mixed>> */
    public array $timelineAllItems = [];

    /** @var list<array<string, mixed>> */
    public array $timelineVisibleItems = [];

    public bool $timelineHasMore = false;

    /** @var array{all: int, support: int, support_coach: int, support_cs: int, assignment_change: int, contract_document: int} */
    public array $timelineTypeTotals = [
        'all' => 0,
        'support' => 0,
        'support_coach' => 0,
        'support_cs' => 0,
        'assignment_change' => 0,
        'contract_document' => 0,
    ];

    public int $timelineHealthScore = 0;

    public bool $showSupportDetailModal = false;

    public ?array $selectedSupportRecord = null;

    public bool $isEditingDetail = false;

    public string $editCustomerType = '';

    public string $editGsNo = '';

    public string $editDetailCo = '';

    public string $editDetailTr = '';

    public string $editDetailCs = '';

    /** 상세 모달 편집: 기관 마스터(S_AccountName) 전체 */
    public string $editDetailSkCode = '';

    public string $editDetailInstitutionName = '';

    public string $editDetailEnglishName = '';

    public string $editDetailPortalName = '';

    public string $editDetailPortalCampusId = '';

    public string $editDetailAccountNo = '';

    public string $editDetailGubun = '';

    public string $editDetailDirector = '';

    public string $editDetailPhone = '';

    public string $editDetailAccountTel = '';

    public string $editDetailAddress = '';

    // ─── 담당자 변경 모달 (InstitutionFormModal 테스트 호환 미러) ─────
    public ?int $editingInstitutionId = null;

    public string $editSkCode = '';

    public string $editInstitutionName = '';

    public string $editCo = '';

    public string $editTr = '';

    public string $editCs = '';

    public function updatingAssignmentFilter(): void
    {
        $this->dispatch('institution-table-reset-page');
    }

    #[On('filter-updated')]
    public function onFilterUpdated(
        string $search,
        string $statusFilter,
        string $filterCo,
        string $filterTr,
        string $filterCs,
        bool $resetAssignment = false,
    ): void {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->filterCo = $filterCo;
        $this->filterTr = $filterTr;
        $this->filterCs = $filterCs;

        if ($resetAssignment) {
            $this->assignmentFilter = '';
        }
    }

    #[On('institution-filter-assignment-cleared')]
    public function clearAssignmentFilter(): void
    {
        $this->assignmentFilter = '';
        $this->dispatch('institution-table-reset-page');
    }

    #[On('institution-row-selected')]
    public function onInstitutionRowSelected(int $institutionId): void
    {
        $this->openDetailModal($institutionId);
    }

    /** @deprecated 테스트·레거시 호출 호환. UI는 InstitutionFilter가 처리합니다. */
    public function clearListFilters(): void
    {
        $this->onFilterUpdated(
            search: '',
            statusFilter: 'all',
            filterCo: '',
            filterTr: '',
            filterCs: '',
            resetAssignment: true,
        );
    }

    public function updatedTimelineTypeFilter(): void
    {
        if ($this->showDetailModal && $this->activeDetailTab === 'timeline') {
            $this->loadTimeline();
        }
    }

    public function updatedTimelineRangeFilter(): void
    {
        if ($this->showDetailModal && $this->activeDetailTab === 'timeline') {
            $this->loadTimeline();
        }
    }

    public function updatedTimelineAuthorFilter(): void
    {
        if ($this->showDetailModal && $this->activeDetailTab === 'timeline') {
            $this->loadTimeline();
        }
    }

    // ─── 컬럼 헤더 클릭 시 정렬 전환 ────────────────────────────────
    public function sort(string $field): void
    {
        if ($this->sortField === $field) {
            // 같은 컬럼을 다시 클릭하면 오름차순 ↔ 내림차순 전환
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // 다른 컬럼을 클릭하면 그 컬럼 기준 오름차순으로 변경
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->dispatch('institution-table-reset-page');
    }

    public function updatedSortField(): void
    {
        $this->dispatch('institution-table-reset-page');
    }

    public function updatedSortDirection(): void
    {
        $this->dispatch('institution-table-reset-page');
    }

    // ─── 기관 행 클릭 시 상세 모달 열기 ────────────────────────────────
    public function openDetailModal(int $id): void
    {
        $institution = $this->resolveInstitutionForDetailModal($id);

        if ($institution->exists) {
            $institution->load($this->institutionEagerLoads());
        } else {
            if ($institution->relationLoaded('accountInfo') === false && filled($institution->SKcode)) {
                $institution->load('accountInfo');
            }

            if (Schema::hasTable('S_GSNumber') && filled($institution->SKcode)) {
                $institution->load('gsNumber');
            }
        }

        $this->hydrateInstitutionDetailMetrics($institution);

        // 저장된 담당자명 표기(예: "Peter.Kim")가 직원 마스터의 옵션 표기(예: "Peter Kim")와
        // 다를 수 있어, select 옵션과 매칭되지 않으면 모달에 "미지정"으로 보이는 문제가 있었다.
        // 같은 사람으로 식별되는 경우 모달 진입 시점에 옵션 표기로 정렬해서 보여준다.
        $aliasedCo = $this->alignManagerLabelToMasterOption(
            $institution->accountInfo?->CO,
            $this->managerOptionsForDept(self::DEPT_CO),
        );
        $aliasedTr = $this->alignManagerLabelToMasterOption(
            $institution->accountInfo?->TR,
            $this->managerOptionsForDept(self::DEPT_TR),
        );
        $aliasedCs = $this->alignManagerLabelToMasterOption(
            $institution->accountInfo?->CS,
            $this->managerOptionsForDept(self::DEPT_CS),
        );
        $managerChangeMeta = $this->resolveLatestManagerChangeMetaByRole((string) ($institution->SKcode ?? ''));

        $this->selectedInstitution = [
            'id' => $institution->ID,
            'skcode' => $institution->SKcode,
            'name' => $institution->resolvedAccountName(),
            'english_name' => $institution->EnglishName,
            'portal_name' => $institution->PortalAccountName,
            'portal_campus_id' => $institution->PortalCampusID,
            'account_no' => $institution->AccountNo,
            'gubun' => $institution->Gubun,
            'director' => $institution->Director,
            'phone' => $institution->Phone,
            'account_tel' => $institution->AccountTel,
            'address' => $institution->Address,
            'co' => $aliasedCo !== '' ? $aliasedCo : null,
            'tr' => $aliasedTr !== '' ? $aliasedTr : null,
            'cs' => $aliasedCs !== '' ? $aliasedCs : null,
            'co_changed_at' => $managerChangeMeta['co_changed_at'],
            'tr_changed_at' => $managerChangeMeta['tr_changed_at'],
            'cs_changed_at' => $managerChangeMeta['cs_changed_at'],
            'co_changed_by' => $managerChangeMeta['co_changed_by'],
            'tr_changed_by' => $managerChangeMeta['tr_changed_by'],
            'cs_changed_by' => $managerChangeMeta['cs_changed_by'],
            'customer_type' => $institution->accountInfo?->Customer_Type,
            'gs_no' => ($resolvedGs = $institution->resolvedGsNumber()) !== '' ? $resolvedGs : null,
            'teacher_count' => $institution->teachers_count,
            'support_count' => $institution->support_records_count,
            'latest_support_date' => $institution->support_records_max_support_date,
        ];

        $this->isEditingDetail = false;
        $this->editCustomerType = (string) ($institution->accountInfo?->Customer_Type ?? '');
        $this->editGsNo = $institution->resolvedGsNumber();
        $this->editDetailCo = $aliasedCo;
        $this->editDetailTr = $aliasedTr;
        $this->editDetailCs = $aliasedCs;
        $this->editDetailSkCode = (string) ($institution->SKcode ?? '');
        $this->editDetailInstitutionName = $institution->resolvedAccountName();
        $this->editDetailEnglishName = (string) ($institution->EnglishName ?? '');
        $this->editDetailPortalName = (string) ($institution->PortalAccountName ?? '');
        $this->editDetailPortalCampusId = (string) ($institution->PortalCampusID ?? '');
        $this->editDetailAccountNo = (string) ($institution->AccountNo ?? '');
        $this->editDetailGubun = (string) ($institution->Gubun ?? '');
        $this->editDetailDirector = (string) ($institution->Director ?? '');
        $this->editDetailPhone = (string) ($institution->Phone ?? '');
        $this->editDetailAccountTel = (string) ($institution->AccountTel ?? '');
        $this->editDetailAddress = (string) ($institution->Address ?? '');

        $this->teamSupportHistory = app(InstitutionTeamSupportHistoryBuilder::class)->build($institution);
        $this->activeSupportTeamTab = $this->resolveDefaultSupportTeamTab($this->teamSupportHistory);
        $this->showUnknownSupportSection = false;
        $this->resetTimelineState();
        $this->activeDetailTab = 'overview';

        $this->showDetailModal = true;

        $this->dispatch(
            'institution-form-set-detail-context',
            institution: $this->selectedInstitution,
        );
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedInstitution = null;
        $this->teamSupportHistory = [];
        $this->activeSupportTeamTab = SupportAuthorTeamResolver::TEAM_CO;
        $this->showUnknownSupportSection = false;
        $this->resetTimelineState();
        $this->activeDetailTab = 'overview';
        $this->isEditingDetail = false;
        $this->editCustomerType = '';
        $this->editGsNo = '';
        $this->editDetailCo = '';
        $this->editDetailTr = '';
        $this->editDetailCs = '';
        $this->editDetailSkCode = '';
        $this->editDetailInstitutionName = '';
        $this->editDetailEnglishName = '';
        $this->editDetailPortalName = '';
        $this->editDetailPortalCampusId = '';
        $this->editDetailAccountNo = '';
        $this->editDetailGubun = '';
        $this->editDetailDirector = '';
        $this->editDetailPhone = '';
        $this->editDetailAccountTel = '';
        $this->editDetailAddress = '';
        $this->resetValidation();
        $this->closeSupportDetailModal();
        $this->closeAllTeacherSupportReportModals();

        $this->dispatch('institution-form-reset-detail');
    }

    public function setDetailTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'timeline'], true)) {
            return;
        }

        $this->activeDetailTab = $tab;

        if ($tab === 'timeline') {
            $this->isEditingDetail = false;
        }

        if ($tab === 'timeline' && $this->timelineAllItems === []) {
            $this->loadTimeline();
        }
    }

    public function loadTimeline(): void
    {
        $skCode = trim((string) ($this->selectedInstitution['skcode'] ?? ''));
        if ($skCode === '') {
            $this->resetTimelineState();

            return;
        }

        $timeline = app(InstitutionUnifiedTimelineBuilder::class)->build(
            $skCode,
            [
                'type' => $this->timelineTypeFilter,
                'range' => $this->timelineRangeFilter,
                'author' => $this->timelineAuthorFilter,
            ],
            300
        );

        $this->timelineAllItems = $timeline['items'];
        $this->timelineTypeTotals = $timeline['totals'];
        $this->timelineVisibleCount = 30;
        $this->refreshVisibleTimelineItems();
        $this->timelineHealthScore = $this->resolveTimelineHealthScore();
    }

    public function loadMoreTimeline(): void
    {
        if (! $this->timelineHasMore) {
            return;
        }

        $this->timelineVisibleCount += 30;
        $this->refreshVisibleTimelineItems();
    }

    private function refreshVisibleTimelineItems(): void
    {
        $this->timelineVisibleItems = array_slice($this->timelineAllItems, 0, $this->timelineVisibleCount);
        $this->timelineHasMore = count($this->timelineAllItems) > count($this->timelineVisibleItems);
    }

    private function resetTimelineState(): void
    {
        $this->timelineTypeFilter = 'all';
        $this->timelineRangeFilter = '6m';
        $this->timelineAuthorFilter = '';
        $this->timelineVisibleCount = 30;
        $this->timelineAllItems = [];
        $this->timelineVisibleItems = [];
        $this->timelineHasMore = false;
        $this->timelineTypeTotals = [
            'all' => 0,
            'support' => 0,
            'support_coach' => 0,
            'support_cs' => 0,
            'assignment_change' => 0,
            'contract_document' => 0,
        ];
        $this->timelineHealthScore = 0;
    }

    private function resolveTimelineHealthScore(): int
    {
        $supportCount = (int) ($this->selectedInstitution['support_count'] ?? 0);
        $teacherCount = (int) ($this->selectedInstitution['teacher_count'] ?? 0);
        $hasCo = trim((string) ($this->selectedInstitution['co'] ?? '')) !== '';
        $hasTr = trim((string) ($this->selectedInstitution['tr'] ?? '')) !== '';
        $hasCs = trim((string) ($this->selectedInstitution['cs'] ?? '')) !== '';

        $score = 35;
        $score += min(25, $supportCount * 2);
        $score += min(12, $teacherCount);
        $score += $hasCo ? 8 : 0;
        $score += $hasTr ? 8 : 0;
        $score += $hasCs ? 8 : 0;

        $latestSupportDate = trim((string) ($this->selectedInstitution['latest_support_date'] ?? ''));
        if ($latestSupportDate !== '') {
            try {
                $days = Carbon::parse($latestSupportDate)->diffInDays(now());
                if ($days <= 60) {
                    $score += 16;
                } elseif ($days <= 120) {
                    $score += 8;
                }
            } catch (\Throwable) {
                // ignore parse error for temporary MVP formula
            }
        }

        return max(0, min(100, $score));
    }

    protected function expectedSupportHistorySkCodeForDetail(): ?string
    {
        $skCode = trim((string) ($this->selectedInstitution['skcode'] ?? ''));

        return $skCode !== '' ? $skCode : null;
    }

    /**
     * @param  array<string, mixed>  $teamSupportHistory
     */
    private function resolveDefaultSupportTeamTab(array $teamSupportHistory): string
    {
        $userTeam = match (TeamMenuContext::activeMenu()) {
            TeamMenuContext::MENU_CO => SupportAuthorTeamResolver::TEAM_CO,
            TeamMenuContext::MENU_COACH => SupportAuthorTeamResolver::TEAM_COACH,
            TeamMenuContext::MENU_CS => SupportAuthorTeamResolver::TEAM_CS,
            default => null,
        };

        if ($userTeam !== null && $this->teamBucketHasRecords($teamSupportHistory, $userTeam)) {
            return $userTeam;
        }

        foreach ([
            SupportAuthorTeamResolver::TEAM_CO,
            SupportAuthorTeamResolver::TEAM_COACH,
            SupportAuthorTeamResolver::TEAM_CS,
        ] as $team) {
            if ($this->teamBucketHasRecords($teamSupportHistory, $team)) {
                return $team;
            }
        }

        return SupportAuthorTeamResolver::TEAM_CO;
    }

    /**
     * @param  array<string, mixed>  $teamSupportHistory
     */
    private function teamBucketHasRecords(array $teamSupportHistory, string $team): bool
    {
        $bucket = $teamSupportHistory[$team] ?? ['institution' => [], 'teacher' => []];

        return count($bucket['institution'] ?? []) + count($bucket['teacher'] ?? []) > 0;
    }

    #[On('institution-form-detail-edit-state')]
    public function onDetailEditState(bool $isEditing): void
    {
        $this->isEditingDetail = $isEditing;
    }

    #[On('institution-saved')]
    public function onInstitutionSaved(string $mode, int $institutionId, string $skCode): void
    {
        if ($mode === 'detail') {
            $this->isEditingDetail = false;
            $this->refreshDetailModalAfterSave($institutionId);

            return;
        }

        if ($mode !== 'manager' || ! $this->showDetailModal || ! $this->selectedInstitution) {
            return;
        }

        $currentId = (int) ($this->selectedInstitution['id'] ?? 0);
        $currentSk = (string) ($this->selectedInstitution['skcode'] ?? '');

        if ($currentId === $institutionId || $currentSk === $skCode) {
            $this->openDetailModal($institutionId > 0 ? $institutionId : $currentId);
        }
    }

    public function startDetailEdit(): void
    {
        if (! $this->selectedInstitution) {
            return;
        }

        $this->isEditingDetail = true;
        $this->editCustomerType = (string) ($this->selectedInstitution['customer_type'] ?? '');
        $this->editGsNo = (string) ($this->selectedInstitution['gs_no'] ?? '');
        $this->editDetailCo = (string) ($this->selectedInstitution['co'] ?? '');
        $this->editDetailTr = (string) ($this->selectedInstitution['tr'] ?? '');
        $this->editDetailCs = (string) ($this->selectedInstitution['cs'] ?? '');
        $this->editDetailSkCode = (string) ($this->selectedInstitution['skcode'] ?? '');
        $this->editDetailInstitutionName = (string) ($this->selectedInstitution['name'] ?? '');
        $this->editDetailEnglishName = (string) ($this->selectedInstitution['english_name'] ?? '');
        $this->editDetailPortalName = (string) ($this->selectedInstitution['portal_name'] ?? '');
        $this->editDetailPortalCampusId = (string) ($this->selectedInstitution['portal_campus_id'] ?? '');
        $this->editDetailAccountNo = (string) ($this->selectedInstitution['account_no'] ?? '');
        $this->editDetailGubun = (string) ($this->selectedInstitution['gubun'] ?? '');
        $this->editDetailDirector = (string) ($this->selectedInstitution['director'] ?? '');
        $this->editDetailPhone = (string) ($this->selectedInstitution['phone'] ?? '');
        $this->editDetailAccountTel = (string) ($this->selectedInstitution['account_tel'] ?? '');
        $this->editDetailAddress = (string) ($this->selectedInstitution['address'] ?? '');
        $this->resetValidation();

        $this->dispatch(
            'institution-form-start-detail-edit',
            institution: $this->selectedInstitution,
        );
    }

    public function cancelDetailEdit(): void
    {
        if (! $this->selectedInstitution) {
            return;
        }

        $this->isEditingDetail = false;
        $this->editCustomerType = (string) ($this->selectedInstitution['customer_type'] ?? '');
        $this->editGsNo = (string) ($this->selectedInstitution['gs_no'] ?? '');
        $this->editDetailCo = (string) ($this->selectedInstitution['co'] ?? '');
        $this->editDetailTr = (string) ($this->selectedInstitution['tr'] ?? '');
        $this->editDetailCs = (string) ($this->selectedInstitution['cs'] ?? '');
        $this->editDetailSkCode = (string) ($this->selectedInstitution['skcode'] ?? '');
        $this->editDetailInstitutionName = (string) ($this->selectedInstitution['name'] ?? '');
        $this->editDetailEnglishName = (string) ($this->selectedInstitution['english_name'] ?? '');
        $this->editDetailPortalName = (string) ($this->selectedInstitution['portal_name'] ?? '');
        $this->editDetailPortalCampusId = (string) ($this->selectedInstitution['portal_campus_id'] ?? '');
        $this->editDetailAccountNo = (string) ($this->selectedInstitution['account_no'] ?? '');
        $this->editDetailGubun = (string) ($this->selectedInstitution['gubun'] ?? '');
        $this->editDetailDirector = (string) ($this->selectedInstitution['director'] ?? '');
        $this->editDetailPhone = (string) ($this->selectedInstitution['phone'] ?? '');
        $this->editDetailAccountTel = (string) ($this->selectedInstitution['account_tel'] ?? '');
        $this->editDetailAddress = (string) ($this->selectedInstitution['address'] ?? '');
        $this->resetValidation();

        $this->dispatch('institution-form-cancel-detail-edit');
    }

    public function saveDetailFields(UpdateInstitutionDetail $updateInstitutionDetail): void
    {
        $result = $this->persistInstitutionDetailFields($updateInstitutionDetail);

        if ($result === null) {
            return;
        }

        $this->refreshDetailModalAfterSave($result['institution_id']);
    }

    private function refreshDetailModalAfterSave(int $institutionId): void
    {
        if ($this->institutionDetailModalIsVisible($institutionId)) {
            $this->openDetailModal($institutionId);
        } else {
            $this->closeDetailModal();
        }
    }

    private function institutionDetailModalIsVisible(int $institutionId): bool
    {
        if ($institutionId <= 0) {
            return false;
        }

        if ($this->catalogRowExistsById($institutionId)) {
            return true;
        }

        $institution = Institution::query()->find($institutionId);
        if ($institution === null) {
            return false;
        }

        return $this->catalogRowExistsBySkCode((string) $institution->SKcode);
    }

    // ─── 지원/소통 이력 상세 모달 ─────────────────────────────────────
    public function openSupportDetailModal(int $supportId): void
    {
        $skCode = $this->selectedInstitution['skcode'] ?? null;
        if (blank($skCode)) {
            return;
        }

        $record = SupportRecord::query()
            ->where('ID', $supportId)
            ->where('SK_Code', $skCode)
            ->firstOrFail();

        $this->selectedSupportRecord = $this->mapSupportRecordToDetailArray($record);
        $this->supportDetailEditMode = false;
        $this->resetSupportDetailEditForm();
        $this->showSupportDetailModal = true;
    }

    public function closeSupportDetailModal(): void
    {
        $this->showSupportDetailModal = false;
        $this->selectedSupportRecord = null;
        $this->resetSupportDetailEditState();
    }

    protected function reloadSupportDetailAfterUpdate(SupportRecord $record): void
    {
        $institutionId = (int) ($this->selectedInstitution['id'] ?? 0);
        if ($institutionId <= 0) {
            $this->selectedSupportRecord = $this->mapSupportRecordToDetailArray($record);

            return;
        }

        $this->openDetailModal($institutionId);
        $this->openSupportDetailModal((int) $record->ID);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapSupportRecordToDetailArray(SupportRecord $record): array
    {
        return [
            'id' => $record->ID,
            'support_date' => $record->Support_Date?->format('Y-m-d') ?? '-',
            'support_time' => $this->formatSupportMeetTime($record->Meet_Time),
            'tr_name' => $record->TR_Name ?? '-',
            'support_type' => $record->Support_Type ?? '-',
            'target' => $record->Target ?? '-',
            'issue' => $record->Issue ?? '-',
            'to_account' => $record->TO_Account ?? '-',
            'to_depart' => $record->TO_Depart ?? '-',
            'others' => $record->Others ?? '-',
            'status' => $record->isCompleted() ? SupportRecord::STATUS_COMPLETED : SupportRecord::STATUS_IN_PROGRESS,
            'created_date' => $record->CreatedDate?->format('Y-m-d H:i') ?? '-',
            'completed_date' => $record->CompletedDate?->format('Y-m-d H:i') ?? '-',
            'can_edit' => $this->resolveSupportRecordCanEdit($record),
        ];
    }

    // ─── 담당자 변경 모달 열기/닫기/저장 ─────────────────────────────
    public function openManagerModal(int $id): void
    {
        $institution = $this->resolveInstitutionForDetailModal($id);
        $institution->loadMissing('accountInfo');

        $this->editingInstitutionId = $institution->ID;
        $this->editSkCode = (string) ($institution->SKcode ?? '');
        $this->editInstitutionName = $institution->resolvedAccountName();
        $this->editCo = $this->alignManagerLabelToMasterOption(
            $institution->accountInfo?->CO,
            $this->managerOptionsForDept(self::DEPT_CO),
        );
        $this->editTr = $this->alignManagerLabelToMasterOption(
            $institution->accountInfo?->TR,
            $this->managerOptionsForDept(self::DEPT_TR),
        );
        $this->editCs = $this->alignManagerLabelToMasterOption(
            $institution->accountInfo?->CS,
            $this->managerOptionsForDept(self::DEPT_CS),
        );

        $this->dispatch(
            'institution-form-open-manager',
            institutionId: $institution->ID,
            skCode: (string) ($institution->SKcode ?? ''),
            institutionName: $institution->resolvedAccountName(),
            co: $this->editCo,
            tr: $this->editTr,
            cs: $this->editCs,
        );
    }

    public function closeManagerModal(): void
    {
        $this->editingInstitutionId = null;
        $this->editSkCode = '';
        $this->editInstitutionName = '';
        $this->editCo = '';
        $this->editTr = '';
        $this->editCs = '';
        $this->resetValidation();

        $this->dispatch('institution-form-close-manager');
    }

    public function saveManagers(UpdateInstitutionManagers $updateInstitutionManagers): void
    {
        $this->persistInstitutionManagers($updateInstitutionManagers);
        $this->closeManagerModal();
    }

    public function exportInstitutionsExcel(): ?StreamedResponse
    {
        try {
            $accountListQuery = $this->accountListQuery();
            $filters = $this->listFilters();

            $accounts = $accountListQuery->listQueryForExport($filters)->get();

            if ($accounts->isEmpty()) {
                session()->flash('error', '다운로드할 데이터가 없습니다.');

                return null;
            }

            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('기관리스트');

            $headers = [
                'SK코드',
                '기관명',
                'CO',
                'Coach',
                'CS',
                'Type',
                '구분',
                '기관장',
                '연락처',
                '기관연락처',
                '주소',
            ];

            foreach ($headers as $index => $header) {
                $column = chr(65 + $index);
                $sheet->setCellValue($column.'1', $header);
                $sheet->getStyle($column.'1')->getFont()->setBold(true);
            }

            $row = 2;
            foreach ($accounts as $account) {
                $master = $account->institution;

                $sheet->fromArray([
                    (string) ($account->SK_Code ?? ''),
                    (string) ($account->Account_Name ?: ($master?->AccountName ?? '')),
                    (string) ($account->CO ?? ''),
                    (string) ($account->TR ?? ''),
                    (string) ($account->CS ?? ''),
                    (string) ($account->Customer_Type ?? ''),
                    (string) ($master?->Gubun ?? ''),
                    (string) ($master?->Director ?? ''),
                    (string) ($master?->Phone ?? ''),
                    (string) ($master?->AccountTel ?? ''),
                    (string) ($account->Address ?: ($master?->Address ?? '')),
                ], null, 'A'.$row);
                $row++;
            }

            foreach (range('A', 'K') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $fileName = '기관리스트_'.now()->format('Ymd_His').'.xlsx';

            return response()->streamDownload(function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception) {
            session()->flash('error', '엑셀 다운로드 중 오류가 발생했습니다.');

            return null;
        }
    }

    // ─── 화면에 표시할 데이터 가져오기 ───────────────────────────────
    public function render()
    {
        $accountListQuery = $this->accountListQuery();
        $filters = $this->listFilters();
        $hiddenInstitutionSkCodes = $accountListQuery->hiddenInstitutionSkCodes();

        // 상단 요약 카드용 집계 (S_Account_Information 기준)
        $managerColumn = $accountListQuery->currentUserManagerColumn();

        $summaryQuery = fn (): Builder => $accountListQuery->accountInformationSummaryQuery($filters);

        $allInstitutionCount = $summaryQuery()->count();

        $assignmentColumn = $managerColumn ?? 'CO';

        $assignedCoCount = $summaryQuery()
            ->tap(fn (Builder $query) => $accountListQuery->applyManagerAssignedConstraint($query, $assignmentColumn))
            ->count();

        $myAssignedCoCount = $summaryQuery()
            ->tap(fn (Builder $query) => $accountListQuery->applyCurrentUserManagerScopeOnAccountInformation($query))
            ->count();

        $unassignedCoCount = max(0, $allInstitutionCount - $assignedCoCount);

        $this->institutionTableTotal = $accountListQuery->accountInformationListQuery($filters)->count();

        // 기관 구분 목록 (필터 드롭다운용)
        $gubunList = Institution::query()
            ->tap(fn (Builder $query) => $accountListQuery->applyTeamInstitutionScope($query))
            ->tap(fn (Builder $query) => $accountListQuery->applyStatusFilter($query, $filters))
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })
            ->whereNotNull('Gubun')
            ->where('Gubun', '!=', '')
            ->distinct()
            ->pluck('Gubun');

        // 담당자 드롭다운 옵션 (직원 마스터 기준, 부서 매핑 + 활성 직원만)
        //  - CO    -> Consulting Team (A02)
        //  - Coach -> Coach Team (A05)
        //  - CS    -> Customer Support Team (A03)
        // 과거 S_Account_Information 이력값을 그대로 끌어오면 퇴사자/비직원도 후보로 떴기 때문에,
        // employee 테이블의 활성 직원(STATUS=1) 만 영문명 기준으로 노출합니다.
        $coManagerOptions = $this->managerOptionsForDept(self::DEPT_CO);
        $trManagerOptions = $this->managerOptionsForDept(self::DEPT_TR);
        $csManagerOptions = $this->managerOptionsForDept(self::DEPT_CS);

        $customerTypeOptions = AccountInformation::query()
            ->whereNotNull('Customer_Type')
            ->where('Customer_Type', '!=', '')
            ->distinct()
            ->orderBy('Customer_Type')
            ->pluck('Customer_Type');

        return view('livewire.institution-list', [
            'gubunList' => $gubunList,
            'statusScopeLabel' => $accountListQuery->statusScopeLabel($filters),
            'allInstitutionCount' => $allInstitutionCount,
            'assignedCoCount' => $assignedCoCount,
            'myAssignedCoCount' => $myAssignedCoCount,
            'unassignedCoCount' => $unassignedCoCount,
            'coManagerOptions' => $coManagerOptions,
            'trManagerOptions' => $trManagerOptions,
            'csManagerOptions' => $csManagerOptions,
            'customerTypeOptions' => $customerTypeOptions,
            'canEditDetailCore' => $this->canEditInstitutionDetailCore(),
            'canEditDetailCo' => $this->canEditInstitutionDetailCo(),
            'canEditDetailTr' => $this->canEditInstitutionDetailTr(),
            'canEditDetailCs' => $this->canEditInstitutionDetailCs(),
            'canEditInstitutionDetail' => $this->canEditInstitutionDetail(),
            ...$this->coachTeacherSupportReportModalConfigs(),
        ]);
    }

    private function listFilters(): InstitutionListFilters
    {
        return InstitutionListFilters::fromComponent($this);
    }

    private function accountListQuery(): InstitutionAccountListQuery
    {
        return app(InstitutionAccountListQuery::class);
    }

    private function formatSupportMeetTime(mixed $meetTime): string
    {
        if ($meetTime === null) {
            return '-';
        }

        if ($meetTime instanceof \DateTimeInterface) {
            return $meetTime->format('H:i');
        }

        $stringValue = trim((string) $meetTime);
        if ($stringValue === '') {
            return '-';
        }

        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '-';
    }

    /**
     * @return array<int, string>
     */
    private function institutionEagerLoads(): array
    {
        $loads = ['accountInfo'];
        if (Schema::hasTable('S_GSNumber')) {
            $loads[] = 'gsNumber';
        }

        return $loads;
    }

    private function resolveInstitutionForDetailModal(int $id): Institution
    {
        if ($this->catalogRowExistsById($id)) {
            $accountInfo = AccountInformation::query()->find($id);
            if ($accountInfo !== null) {
                return $this->institutionForAccountInformation($accountInfo);
            }

            return Institution::query()->findOrFail($id);
        }

        $institution = Institution::query()->find($id);
        if ($institution !== null && $this->catalogRowExistsBySkCode((string) $institution->SKcode)) {
            $institution->loadMissing('accountInfo');

            return $institution;
        }

        abort(404);
    }

    private function catalogRowExistsById(int $id): bool
    {
        return $this->accountListQuery()
            ->accountInformationListQuery($this->listFilters())
            ->where('ID', $id)
            ->exists();
    }

    private function catalogRowExistsBySkCode(string $skCode): bool
    {
        if ($skCode === '') {
            return false;
        }

        return $this->accountListQuery()
            ->accountInformationListQuery($this->listFilters())
            ->where('SK_Code', $skCode)
            ->exists();
    }

    private function institutionForAccountInformation(AccountInformation $accountInfo): Institution
    {
        $institution = Institution::query()
            ->where('SKcode', $accountInfo->SK_Code)
            ->first();

        if ($institution === null) {
            $institution = new Institution([
                'SKcode' => $accountInfo->SK_Code,
                'AccountName' => $accountInfo->Account_Name,
                'Address' => $accountInfo->Address,
            ]);
        }

        $institution->setRelation('accountInfo', $accountInfo);

        return $institution;
    }

    private function hydrateInstitutionDetailMetrics(Institution $institution): void
    {
        if ($institution->exists) {
            $institution->loadCount(['teachers', 'supportRecords'])
                ->loadMax('supportRecords', 'Support_Date');

            return;
        }

        $skCode = (string) $institution->SKcode;
        if ($skCode === '') {
            $institution->teachers_count = 0;
            $institution->support_records_count = 0;
            $institution->support_records_max_support_date = null;

            return;
        }

        $institution->teachers_count = $institution->teachers()->count();
        $institution->support_records_count = $institution->supportRecords()->count();
        $institution->support_records_max_support_date = $institution->supportRecords()->max('Support_Date');
    }

    /**
     * 부서(WORKDEPT) 기준 활성 직원의 영문명(없으면 한글명) 목록을 옵션으로 반환합니다.
     *
     * - STATUS = 1 (활성)만 대상
     * - ENGLISHNAME 우선, 비어 있으면 KOREANAME 으로 대체 (현장에서 입력값이 영문 기준이라 ENGLISHNAME 우선)
     * - 중복 제거 + 알파벳 정렬
     *
     * @return Collection<int, string>
     */
    private function managerOptionsForDept(string $deptNo): Collection
    {
        if (! Schema::hasTable('employee')) {
            return collect();
        }

        return Employee::query()
            ->where('WORKDEPT', $deptNo)
            ->where('STATUS', 1)
            ->get(['ENGLISHNAME', 'KOREANAME'])
            ->map(function (Employee $employee): string {
                $english = trim((string) ($employee->ENGLISHNAME ?? ''));
                if ($english !== '') {
                    return $english;
                }

                return trim((string) ($employee->KOREANAME ?? ''));
            })
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @return array{
     *     co_changed_at: ?string,
     *     tr_changed_at: ?string,
     *     cs_changed_at: ?string,
     *     co_changed_by: ?string,
     *     tr_changed_by: ?string,
     *     cs_changed_by: ?string
     * }
     */
    private function resolveLatestManagerChangeMetaByRole(string $skCode): array
    {
        $empty = [
            'co_changed_at' => null,
            'tr_changed_at' => null,
            'cs_changed_at' => null,
            'co_changed_by' => null,
            'tr_changed_by' => null,
            'cs_changed_by' => null,
        ];

        if ($skCode === '' || ! Schema::hasTable('assignment_change_requests')) {
            return $empty;
        }

        /** @var Collection<int, AssignmentChangeRequest> $requests */
        $requests = AssignmentChangeRequest::query()
            ->where('sk_code', $skCode)
            ->where('status', '!=', AssignmentChangeRequest::STATUS_FAILED)
            ->orderByDesc(DB::raw('COALESCE(applied_at, requested_at)'))
            ->get(['co', 'tr', 'cs', 'changed_by', 'origin', 'applied_at', 'requested_at']);

        if ($requests->isEmpty()) {
            return $empty;
        }

        $latestChangeMeta = $empty;
        foreach ($requests as $request) {
            $changedAt = $request->applied_at ?? $request->requested_at;
            if ($changedAt === null) {
                continue;
            }

            $formatted = $changedAt->format('Y-m-d');
            $changedBy = $this->resolveManagerChangedByLabel($request);
            foreach (['co', 'tr', 'cs'] as $role) {
                if ($latestChangeMeta["{$role}_changed_at"] !== null) {
                    continue;
                }

                if (filled($request->getAttribute($role))) {
                    $latestChangeMeta["{$role}_changed_at"] = $formatted;
                    $latestChangeMeta["{$role}_changed_by"] = $changedBy;
                }
            }

            if ($latestChangeMeta['co_changed_at'] !== null
                && $latestChangeMeta['tr_changed_at'] !== null
                && $latestChangeMeta['cs_changed_at'] !== null) {
                break;
            }
        }

        return $latestChangeMeta;
    }

    private function resolveManagerChangedByLabel(AssignmentChangeRequest $request): string
    {
        $changedBy = trim((string) ($request->changed_by ?? ''));
        if ($changedBy !== '') {
            return $changedBy;
        }

        return $request->origin === AssignmentChangeRequest::ORIGIN_EXTERNAL
            ? 'External Sync'
            : 'Internal Update';
    }

    /**
     * 저장된 담당자명 표기를 직원 마스터 옵션과 같은 표기로 정렬합니다.
     *
     * - 정규화 키(공백/점 무시)가 같은 옵션을 찾으면 그 표기로 치환합니다.
     * - 옵션 목록에 매칭이 없으면 원본 값을 그대로 둡니다(퇴사자/타부서/예외 케이스 보존).
     * - 사용자가 모달에서 저장을 누를 때 비로소 DB에 마스터 표기로 정리됩니다.
     *
     * @param  Collection<int, string>  $options
     */
    private function alignManagerLabelToMasterOption(?string $raw, Collection $options): string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return '';
        }

        $rawKey = ManagerNameNormalizer::normalize($raw);
        if ($rawKey === '') {
            return $raw;
        }

        foreach ($options as $option) {
            if (ManagerNameNormalizer::normalize((string) $option) === $rawKey) {
                return (string) $option;
            }
        }

        return $raw;
    }
}
