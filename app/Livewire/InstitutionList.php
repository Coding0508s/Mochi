<?php

namespace App\Livewire;

use App\Enums\SyncOrigin;
use App\Jobs\SyncInstitutionOutboundJob;
use App\Livewire\Concerns\ManagesInstitutionSupportDetailEdit;
use App\Livewire\Concerns\OpensTeacherSupportHistoryDetail;
use App\Models\AccountInformation;
use App\Models\AssignmentChangeRequest;
use App\Models\Employee;
use App\Models\GsNumber;
use App\Models\Institution;
use App\Models\SkCodeRequest;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Support\InstitutionCatalog;
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
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class InstitutionList extends Component
{
    use ManagesInstitutionSupportDetailEdit;
    use OpensTeacherSupportHistoryDetail;
    use WithPagination;
    // WithPagination: "다음 페이지", "이전 페이지" 기능을 자동으로 제공합니다.

    // ─── 담당자 드롭다운 부서 매핑 ─────────────────────────────────
    // 상세 모달의 CO / Coach / CS 드롭다운은 아래 부서(WORKDEPT) 활성 직원만 후보로 노출합니다.
    private const DEPT_CO = 'A02'; // Consulting Team

    private const DEPT_TR = 'A05'; // Coach Team

    private const DEPT_CS = 'A03'; // Customer Support Team

    // ─── 검색/필터 상태 ────────────────────────────────────────────
    public string $search = '';
    // 상단 검색창에 입력된 텍스트. 빈 문자열로 시작합니다.

    public string $statusFilter = 'all';
    // 기관 상태 필터: active | terminated | all (기본: S_Account_Information 전체 = phpMyAdmin 행 수와 동일)

    public string $assignmentFilter = '';
    // 담당자 배정 상태 필터: '' | assigned | unassigned | my_assigned

    public string $sortField = 'FGC_CreateDate';
    // 현재 정렬 기준 컬럼 (기본: S_Account_Information.FGC_CreateDate)

    public string $sortDirection = 'asc';
    // 정렬 방향: asc(오름차순) / desc(내림차순)

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

    // ─── 담당자 변경 모달 상태 ───────────────────────────────────────
    public bool $showManagerModal = false;

    public ?int $editingInstitutionId = null;

    public string $editSkCode = '';

    public string $editInstitutionName = '';

    public string $editCo = '';

    public string $editTr = '';

    public string $editCs = '';

    // ─── 검색어가 바뀌면 자동으로 1페이지로 돌아가기 ──────────────
    public function updatingSearch(): void
    {
        $this->resetPage();
        // 검색어가 바뀌었을 때 2페이지에 있다면 자동으로 1페이지로 이동합니다.
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingAssignmentFilter(): void
    {
        $this->resetPage();
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
        $this->resetPage();
    }

    // ─── 기관 행 클릭 시 상세 모달 열기 ────────────────────────────────
    public function openDetailModal(int $id): void
    {
        $institution = $this->resolveInstitutionForDetailModal($id)
            ->load($this->institutionEagerLoads())
            ->loadCount(['teachers', 'supportRecords'])
            ->loadMax('supportRecords', 'Support_Date');

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
    }

    public function setDetailTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'timeline'], true)) {
            return;
        }

        $this->activeDetailTab = $tab;

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
    }

    public function saveDetailFields(): void
    {
        if (! $this->selectedInstitution) {
            return;
        }

        if (! $this->canEditInstitutionDetail()) {
            $this->addError('detailEdit', '기관 상세 정보를 수정할 권한이 없습니다.');

            return;
        }

        $institutionId = (int) ($this->selectedInstitution['id'] ?? 0);
        $originalSk = trim((string) ($this->selectedInstitution['skcode'] ?? ''));

        if ($institutionId <= 0 || $originalSk === '') {
            return;
        }

        $this->applyInstitutionDetailEditFieldLocks();

        $this->validate([
            'editDetailSkCode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('S_AccountName', 'SKcode')->ignore($institutionId, 'ID'),
            ],
            'editDetailInstitutionName' => ['required', 'string', 'max:255'],
            'editDetailEnglishName' => ['nullable', 'string', 'max:255'],
            'editDetailPortalName' => ['nullable', 'string', 'max:255'],
            'editDetailPortalCampusId' => ['nullable', 'string', 'max:100'],
            'editDetailAccountNo' => ['nullable', 'string', 'max:100'],
            'editDetailGubun' => ['nullable', 'string', 'max:100'],
            'editDetailDirector' => ['nullable', 'string', 'max:255'],
            'editDetailPhone' => ['nullable', 'string', 'max:100'],
            'editDetailAccountTel' => ['nullable', 'string', 'max:100'],
            'editDetailAddress' => ['nullable', 'string', 'max:500'],
            'editCustomerType' => ['nullable', 'string', 'max:255'],
            'editGsNo' => ['nullable', 'string', 'max:255'],
            'editDetailCo' => ['nullable', 'string', 'max:255'],
            'editDetailTr' => ['nullable', 'string', 'max:255'],
            'editDetailCs' => ['nullable', 'string', 'max:255'],
        ], [
            'editDetailSkCode.required' => 'SK 코드를 입력해 주세요.',
            'editDetailSkCode.unique' => '이미 사용 중인 SK 코드입니다.',
            'editDetailInstitutionName.required' => '기관명을 입력해 주세요.',
        ]);

        $institution = Institution::query()->findOrFail($institutionId);
        $oldSk = trim((string) $institution->SKcode);
        $newSk = trim($this->editDetailSkCode);
        $trimmedGs = trim($this->editGsNo);
        $accountName = trim($this->editDetailInstitutionName);
        $beforeAccountInfo = AccountInformation::query()
            ->where('SK_Code', $newSk)
            ->first();

        DB::transaction(function () use ($institution, $oldSk, $newSk, $accountName, $trimmedGs, $beforeAccountInfo): void {
            if ($oldSk !== $newSk) {
                if (Schema::hasTable('Teachers')) {
                    Teacher::query()->where('SK_Code', $oldSk)->update(['SK_Code' => $newSk]);
                }
                SupportRecord::query()->where('SK_Code', $oldSk)->update(['SK_Code' => $newSk]);
                if (Schema::hasTable('S_GSNumber')) {
                    GsNumber::query()->where('SKCode', $oldSk)->update(['SKCode' => $newSk]);
                }
                AccountInformation::query()->where('SK_Code', $oldSk)->update(['SK_Code' => $newSk]);
                if (Schema::hasTable('institution_visibility_overrides')) {
                    DB::table('institution_visibility_overrides')
                        ->where('sk_code', $oldSk)
                        ->update(['sk_code' => $newSk, 'updated_at' => now()]);
                }
            }

            $institution->update([
                'SKcode' => $newSk,
                'AccountName' => $accountName,
                'EnglishName' => trim($this->editDetailEnglishName) !== '' ? trim($this->editDetailEnglishName) : null,
                'PortalAccountName' => trim($this->editDetailPortalName) !== '' ? trim($this->editDetailPortalName) : null,
                'PortalCampusID' => trim($this->editDetailPortalCampusId) !== '' ? trim($this->editDetailPortalCampusId) : null,
                'AccountNo' => trim($this->editDetailAccountNo) !== '' ? trim($this->editDetailAccountNo) : null,
                'Director' => trim($this->editDetailDirector) !== '' ? trim($this->editDetailDirector) : null,
                'Phone' => trim($this->editDetailPhone) !== '' ? trim($this->editDetailPhone) : null,
                'AccountTel' => trim($this->editDetailAccountTel) !== '' ? trim($this->editDetailAccountTel) : null,
                'Address' => trim($this->editDetailAddress) !== '' ? trim($this->editDetailAddress) : null,
                'Gubun' => trim($this->editDetailGubun) !== '' ? trim($this->editDetailGubun) : null,
                'GSno' => $trimmedGs !== '' ? $trimmedGs : null,
            ]);

            AccountInformation::query()->updateOrCreate(
                ['SK_Code' => $newSk],
                [
                    'Account_Name' => $accountName,
                    'Customer_Type' => trim($this->editCustomerType) ?: null,
                    'CO' => trim($this->editDetailCo) ?: null,
                    'TR' => trim($this->editDetailTr) ?: null,
                    'CS' => trim($this->editDetailCs) ?: null,
                    'Address' => trim($this->editDetailAddress) !== '' ? trim($this->editDetailAddress) : null,
                ]
            );

            $this->reverseSyncToSkCodeRequest($newSk, [
                'institution_name' => $accountName,
                'portal_campus_id' => trim($this->editDetailPortalCampusId) !== '' ? trim($this->editDetailPortalCampusId) : null,
                'account_no' => trim($this->editDetailAccountNo) !== '' ? trim($this->editDetailAccountNo) : null,
                'co' => trim($this->editDetailCo) ?: null,
                'tr' => trim($this->editDetailTr) ?: null,
                'cs' => trim($this->editDetailCs) ?: null,
            ]);
            $this->enqueueAssignmentChangeRequestIfNeeded(
                $newSk,
                $beforeAccountInfo,
                [
                    'co' => trim($this->editDetailCo) ?: null,
                    'tr' => trim($this->editDetailTr) ?: null,
                    'cs' => trim($this->editDetailCs) ?: null,
                ]
            );

            if (Schema::hasTable('S_GSNumber')) {
                GsNumber::query()->updateOrCreate(
                    ['SKCode' => $newSk],
                    [
                        'AccountName' => $accountName !== '' ? $accountName : null,
                        'GSnumber' => $trimmedGs !== '' ? $trimmedGs : null,
                    ]
                );
            }

            DB::afterCommit(function () use ($newSk): void {
                SyncInstitutionOutboundJob::dispatchIf(
                    (bool) config('services.institution_outbound.enabled'),
                    $newSk,
                    SyncOrigin::Local
                );
            });
        });

        if ($this->institutionDetailModalIsVisible($institutionId)) {
            $this->openDetailModal($institutionId);
        } else {
            $this->closeDetailModal();
        }

        $this->resetValidation();
        session()->flash('success', '기관 상세 정보가 저장되었습니다.');
    }

    private function institutionDetailModalIsVisible(int $institutionId): bool
    {
        if ($institutionId <= 0) {
            return false;
        }

        return AccountInformation::query()
            ->tap(fn (Builder $query) => $this->applyTeamAccountInformationScope($query))
            ->whereKey($institutionId)
            ->exists()
            || Institution::query()
                ->tap(fn (Builder $query) => $this->applyTeamInstitutionScope($query))
                ->whereKey($institutionId)
                ->exists();
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
        $this->showManagerModal = true;
    }

    public function closeManagerModal(): void
    {
        $this->showManagerModal = false;
        $this->editingInstitutionId = null;
        $this->editSkCode = '';
        $this->editInstitutionName = '';
        $this->editCo = '';
        $this->editTr = '';
        $this->editCs = '';
        $this->resetValidation();
    }

    public function saveManagers(): void
    {
        if (! $this->canEditInstitutionDetail()) {
            $this->addError('managerEdit', '담당자 정보를 수정할 권한이 없습니다.');

            return;
        }

        $existing = AccountInformation::query()
            ->where('SK_Code', $this->editSkCode)
            ->first();

        $co = $this->canEditInstitutionDetailCo()
            ? trim($this->editCo) ?: null
            : ($existing?->CO);
        $tr = $this->canEditInstitutionDetailTr()
            ? trim($this->editTr) ?: null
            : ($existing?->TR);
        $cs = $this->canEditInstitutionDetailCs()
            ? trim($this->editCs) ?: null
            : ($existing?->CS);

        $this->validate([
            'editSkCode' => 'required',
            'editInstitutionName' => 'required|string|max:255',
            'editCo' => 'nullable|string|max:255',
            'editTr' => 'nullable|string|max:255',
            'editCs' => 'nullable|string|max:255',
        ], [
            'editSkCode.required' => '기관 코드가 필요합니다.',
            'editInstitutionName.required' => '기관명이 필요합니다.',
        ]);

        $accountName = trim($this->editInstitutionName);

        DB::transaction(function () use ($accountName, $co, $tr, $cs, $existing): void {
            Institution::query()
                ->where('SKcode', $this->editSkCode)
                ->update(['AccountName' => $accountName]);

            AccountInformation::query()->updateOrCreate(
                ['SK_Code' => $this->editSkCode],
                [
                    'Account_Name' => $accountName,
                    'CO' => $co,
                    'TR' => $tr,
                    'CS' => $cs,
                ]
            );

            if (Schema::hasTable('S_GSNumber')) {
                GsNumber::query()->updateOrCreate(
                    ['SKCode' => $this->editSkCode],
                    ['AccountName' => $accountName !== '' ? $accountName : null],
                );
            }

            $this->reverseSyncToSkCodeRequest($this->editSkCode, [
                'institution_name' => $accountName,
                'co' => $co,
                'tr' => $tr,
                'cs' => $cs,
            ]);
            $this->enqueueAssignmentChangeRequestIfNeeded(
                $this->editSkCode,
                $existing,
                ['co' => $co, 'tr' => $tr, 'cs' => $cs]
            );

            DB::afterCommit(function (): void {
                SyncInstitutionOutboundJob::dispatchIf(
                    (bool) config('services.institution_outbound.enabled'),
                    $this->editSkCode,
                    SyncOrigin::Local
                );
            });
        });

        // 상세 모달 열려 있으면 즉시 표시값도 갱신
        if ($this->selectedInstitution && $this->selectedInstitution['skcode'] === $this->editSkCode) {
            $this->selectedInstitution['name'] = $accountName;
            $this->selectedInstitution['co'] = $co;
            $this->selectedInstitution['tr'] = $tr;
            $this->selectedInstitution['cs'] = $cs;
        }

        session()->flash('success', '담당자 정보가 저장되었습니다.');
        $this->closeManagerModal();
    }

    // ─── 화면에 표시할 데이터 가져오기 ───────────────────────────────
    public function render()
    {
        $hiddenInstitutionSkCodes = $this->hiddenInstitutionSkCodes();

        // 상단 요약 카드용 집계 (S_Account_Information 기준)
        $managerColumn = $this->currentUserManagerColumn();

        $summaryQuery = fn (): Builder => $this->accountInformationSummaryQuery($hiddenInstitutionSkCodes);

        $allInstitutionCount = $summaryQuery()->count();

        $assignmentColumn = $managerColumn ?? 'CO';

        $assignedCoCount = $summaryQuery()
            ->tap(fn (Builder $query) => $this->applyManagerAssignedConstraint($query, $assignmentColumn))
            ->count();

        $myAssignedCoCount = $summaryQuery()
            ->tap(fn (Builder $query) => $this->applyCurrentUserManagerScopeOnAccountInformation($query))
            ->count();

        $unassignedCoCount = max(0, $allInstitutionCount - $assignedCoCount);

        $institutions = $this->accountInformationListQuery($hiddenInstitutionSkCodes, $assignmentColumn)
            ->with($this->accountInformationEagerLoads())
            ->tap(fn (Builder $query) => $this->applyAccountInformationListSort($query))
            ->paginate(20);
        // 한 페이지에 20개씩 표시합니다. (목록 SSOT: S_Account_Information)

        // 기관 구분 목록 (필터 드롭다운용)
        $gubunList = Institution::query()
            ->tap(fn (Builder $query) => $this->applyTeamInstitutionScope($query))
            ->tap(fn (Builder $query) => $this->applyStatusFilter($query))
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
            'institutions' => $institutions,
            'gubunList' => $gubunList,
            'statusScopeLabel' => $this->statusScopeLabel(),
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

    public function canEditInstitutionDetail(): bool
    {
        return $this->canEditInstitutionDetailCore()
            || $this->canEditInstitutionDetailCo()
            || $this->canEditInstitutionDetailTr()
            || $this->canEditInstitutionDetailCs();
    }

    public function canEditInstitutionDetailCore(): bool
    {
        return (bool) auth()->user()?->hasFullAccess();
    }

    public function canEditInstitutionDetailCo(): bool
    {
        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_CO;
    }

    public function canEditInstitutionDetailTr(): bool
    {
        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_TR;
    }

    public function canEditInstitutionDetailCs(): bool
    {
        if ($this->canEditInstitutionDetailCore()) {
            return true;
        }

        return $this->resolveCurrentUserManagerDept() === self::DEPT_CS;
    }

    protected function applyInstitutionDetailEditFieldLocks(): void
    {
        if (! $this->selectedInstitution) {
            return;
        }

        if (! $this->canEditInstitutionDetailCore()) {
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
            $this->editCustomerType = (string) ($this->selectedInstitution['customer_type'] ?? '');
            $this->editGsNo = (string) ($this->selectedInstitution['gs_no'] ?? '');
        }

        if (! $this->canEditInstitutionDetailCo()) {
            $this->editDetailCo = (string) ($this->selectedInstitution['co'] ?? '');
        }

        if (! $this->canEditInstitutionDetailTr()) {
            $this->editDetailTr = (string) ($this->selectedInstitution['tr'] ?? '');
        }

        if (! $this->canEditInstitutionDetailCs()) {
            $this->editDetailCs = (string) ($this->selectedInstitution['cs'] ?? '');
        }
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

    private function resolveCurrentUserManagerDept(): ?string
    {
        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        $workdept = $user->employee?->WORKDEPT;
        if (filled($workdept)) {
            $dept = (string) $workdept;
            if (in_array($dept, [self::DEPT_CO, self::DEPT_TR, self::DEPT_CS], true)) {
                return $dept;
            }
        }

        if ($user->isCoTeam()) {
            return self::DEPT_CO;
        }

        if ($user->isCoachTeam()) {
            return self::DEPT_TR;
        }

        if ($user->isCsTeam()) {
            return self::DEPT_CS;
        }

        return null;
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

    /**
     * @return list<string>
     */
    private function accountInformationEagerLoads(): array
    {
        $loads = ['institution'];
        if (Schema::hasTable('S_GSNumber')) {
            $loads[] = 'institution.gsNumber';
        }

        return $loads;
    }

    /**
     * @param  list<string>  $hiddenInstitutionSkCodes
     */
    private function accountInformationSummaryQuery(array $hiddenInstitutionSkCodes): Builder
    {
        return InstitutionCatalog::query()
            ->when($hiddenInstitutionSkCodes !== [], function (Builder $query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SK_Code', $hiddenInstitutionSkCodes);
            })
            ->tap(fn (Builder $query) => $this->applyStatusFilterOnAccountInformation($query))
            ->tap(fn (Builder $query) => $this->applyTeamAccountInformationScope($query));
    }

    /**
     * @param  list<string>  $hiddenInstitutionSkCodes
     */
    private function accountInformationListQuery(array $hiddenInstitutionSkCodes, string $assignmentColumn): Builder
    {
        return $this->accountInformationSummaryQuery($hiddenInstitutionSkCodes)
            ->search($this->search)
            ->when($this->assignmentFilter === 'assigned', function (Builder $query) use ($assignmentColumn): void {
                $this->applyManagerAssignedConstraint($query, $assignmentColumn);
            })
            ->when($this->assignmentFilter === 'unassigned', function (Builder $query) use ($assignmentColumn): void {
                $query->where(function (Builder $unassignedQuery) use ($assignmentColumn): void {
                    $unassignedQuery->whereNull($assignmentColumn)
                        ->orWhere($assignmentColumn, '');
                });
            })
            ->when($this->assignmentFilter === 'my_assigned', function (Builder $query): void {
                $this->applyCurrentUserManagerScopeOnAccountInformation($query);
            });
    }

    private function resolveInstitutionForDetailModal(int $id): Institution
    {
        $scopedInstitutionQuery = fn (): Builder => Institution::query()
            ->tap(fn (Builder $query) => $this->applyTeamInstitutionScope($query));

        $institution = $scopedInstitutionQuery()->find($id);
        if ($institution !== null) {
            return $institution;
        }

        $accountInfo = AccountInformation::query()
            ->tap(fn (Builder $query) => $this->applyTeamAccountInformationScope($query))
            ->findOrFail($id);

        return $scopedInstitutionQuery()
            ->where('SKcode', $accountInfo->SK_Code)
            ->firstOrFail();
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
     * @return array<int, string>
     */
    private function hiddenInstitutionSkCodes(): array
    {
        if (! Schema::hasTable('institution_visibility_overrides')) {
            return [];
        }

        return DB::table('institution_visibility_overrides')
            ->whereNotNull('hidden_at')
            ->pluck('sk_code')
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 로컬에서 마스터 데이터를 수정하면 sk_code_requests도 같은 값으로 맞춥니다.
     * applied_at을 함께 갱신해 queued job이 동일 값을 다시 적용하지 않게 합니다.
     *
     * @param  array<string, mixed>  $values
     */
    private function reverseSyncToSkCodeRequest(string $skCode, array $values): void
    {
        $request = SkCodeRequest::query()
            ->where('final_sk_code', $skCode)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->first();

        if (! $request) {
            return;
        }

        $patch = [];
        foreach ($values as $column => $value) {
            if ($value === null) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }

            $patch[$column] = $trimmed;
        }

        if ($patch === []) {
            return;
        }

        $syncedAt = now();

        $request->timestamps = false;
        $request->update(array_merge($patch, [
            'applied_at' => $syncedAt,
            'updated_at' => $syncedAt,
        ]));
        $request->timestamps = true;
    }

    private function enqueueAssignmentChangeRequestIfNeeded(
        string $skCode,
        ?AccountInformation $before,
        array $after
    ): void {
        if (! (bool) config('services.assignment_sync.enabled', false)) {
            return;
        }

        $beforeValues = [
            'co' => $this->normalizeManagerValue($before?->CO),
            'tr' => $this->normalizeManagerValue($before?->TR),
            'cs' => $this->normalizeManagerValue($before?->CS),
        ];
        $afterValues = [
            'co' => $this->normalizeManagerValue($after['co'] ?? null),
            'tr' => $this->normalizeManagerValue($after['tr'] ?? null),
            'cs' => $this->normalizeManagerValue($after['cs'] ?? null),
        ];

        $patch = [];
        foreach (['co', 'tr', 'cs'] as $key) {
            if ($beforeValues[$key] === $afterValues[$key]) {
                continue;
            }

            $patch[$key] = $afterValues[$key];
        }

        if ($patch === []) {
            return;
        }

        AssignmentChangeRequest::query()->create([
            'sk_code' => $skCode,
            'co' => $patch['co'] ?? null,
            'tr' => $patch['tr'] ?? null,
            'cs' => $patch['cs'] ?? null,
            'changed_by' => auth()->user()?->nameForCoReports(),
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }

    private function normalizeManagerValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
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

    private function applyStatusFilter(Builder $query): void
    {
        if ($this->statusFilter === 'all') {
            return;
        }

        if ($this->statusFilter === 'terminated') {
            $query->whereHas('accountInfo', function (Builder $sub): void {
                $sub->where('Customer_Type', 'like', '%해지%');
            });

            return;
        }

        $query->where(function (Builder $statusQuery): void {
            $statusQuery->whereDoesntHave('accountInfo')
                ->orWhereHas('accountInfo', function (Builder $sub): void {
                    $sub->where(function (Builder $customerTypeQuery): void {
                        $customerTypeQuery->whereNull('Customer_Type')
                            ->orWhere('Customer_Type', '')
                            ->orWhere('Customer_Type', 'not like', '%해지%');
                    });
                });
        });
    }

    private function statusScopeLabel(): string
    {
        return match ($this->statusFilter) {
            'terminated' => '해지 기관',
            'active' => '운영 기관',
            default => '전체 기관',
        };
    }

    private function applyStatusFilterOnAccountInformation(Builder $query): void
    {
        if ($this->statusFilter === 'all') {
            return;
        }

        if ($this->statusFilter === 'terminated') {
            $query->terminatedCustomers();

            return;
        }

        $query->activeCustomers();
    }

    private function applyTeamInstitutionScope(Builder $query): void
    {
        if (! $this->shouldScopeToAssignedInstitutions()) {
            return;
        }

        $this->applyCurrentUserManagerScope($query);
    }

    private function applyTeamAccountInformationScope(Builder $query): void
    {
        if (! $this->shouldScopeToAssignedInstitutions()) {
            return;
        }

        $this->applyCurrentUserManagerScopeOnAccountInformation($query);
    }

    private function applyCurrentUserManagerScope(Builder $query): void
    {
        $aliases = $this->resolveCurrentUserManagerAliases();
        if ($aliases === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = $this->currentUserManagerColumn();
        if ($column === null) {
            $query->whereHas('accountInfo', function (Builder $sub) use ($aliases): void {
                $sub->where(function (Builder $inner) use ($aliases): void {
                    foreach (['CO', 'TR', 'CS'] as $managerColumn) {
                        $sql = ManagerNameNormalizer::sqlColumnExpression($managerColumn);
                        $inner->orWhere(function (Builder $columnQuery) use ($aliases, $sql): void {
                            foreach ($aliases as $alias) {
                                $columnQuery->orWhereRaw("{$sql} = ?", [$alias]);
                            }
                        });
                    }
                });
            });

            return;
        }

        $sqlNormalized = ManagerNameNormalizer::sqlColumnExpression($column);

        $query->whereHas('accountInfo', function (Builder $sub) use ($aliases, $sqlNormalized): void {
            $sub->where(function (Builder $managerQuery) use ($aliases, $sqlNormalized): void {
                foreach ($aliases as $alias) {
                    $managerQuery->orWhereRaw("{$sqlNormalized} = ?", [$alias]);
                }
            });
        });
    }

    private function applyCurrentUserManagerScopeOnAccountInformation(Builder $query): void
    {
        $aliases = $this->resolveCurrentUserManagerAliases();
        if ($aliases === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = $this->currentUserManagerColumn();
        if ($column === null) {
            $query->where(function (Builder $inner) use ($aliases): void {
                foreach (['CO', 'TR', 'CS'] as $managerColumn) {
                    $sql = ManagerNameNormalizer::sqlColumnExpression($managerColumn);
                    $inner->orWhere(function (Builder $columnQuery) use ($aliases, $sql): void {
                        foreach ($aliases as $alias) {
                            $columnQuery->orWhereRaw("{$sql} = ?", [$alias]);
                        }
                    });
                }
            });

            return;
        }

        $query->whereManagerMatches($column, $aliases);
    }

    private function applyManagerAssignedConstraint(Builder $query, string $column): void
    {
        $query->whereNotNull($column)
            ->where($column, '!=', '');
    }

    private function applyInstitutionSort(Builder $query): void
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        if ($this->sortField === 'AccountName') {
            $query->leftJoin('S_Account_Information', 'S_AccountName.SKcode', '=', 'S_Account_Information.SK_Code')
                ->orderByRaw(
                    "COALESCE(NULLIF(S_Account_Information.Account_Name, ''), S_AccountName.AccountName) {$direction}"
                )
                ->orderBy('S_AccountName.SKcode');

            return;
        }

        $sortableFields = ['SKcode', 'GSno', 'Gubun', 'Director', 'Phone', 'AccountTel'];
        $field = in_array($this->sortField, $sortableFields, true) ? $this->sortField : 'SKcode';
        $query->orderBy("S_AccountName.{$field}", $direction);
    }

    private function applyAccountInformationListSort(Builder $query): void
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        if ($this->sortField === 'FGC_CreateDate' && Schema::hasColumn('S_Account_Information', 'FGC_CreateDate')) {
            $nullsOrder = $direction === 'asc' ? 'ASC' : 'DESC';
            $query->orderByRaw("FGC_CreateDate IS NULL {$nullsOrder}")
                ->orderBy('FGC_CreateDate', $direction);

            if (Schema::hasColumn('S_Account_Information', 'ID')) {
                $query->orderBy('ID', $direction);
            }

            return;
        }

        if ($this->sortField === 'AccountName') {
            $query->orderBy('Account_Name', $direction)
                ->orderBy('SK_Code');

            return;
        }

        if ($this->sortField === 'SKcode') {
            $query->orderBy('SK_Code', $direction);

            return;
        }

        $sortableOnMaster = ['GSno', 'Gubun', 'Director', 'Phone', 'AccountTel'];
        if (in_array($this->sortField, $sortableOnMaster, true)) {
            $field = $this->sortField;
            $query->leftJoin('S_AccountName', 'S_Account_Information.SK_Code', '=', 'S_AccountName.SKcode')
                ->orderBy("S_AccountName.{$field}", $direction)
                ->select('S_Account_Information.*');

            return;
        }

        if (Schema::hasColumn('S_Account_Information', 'FGC_CreateDate')) {
            $nullsOrder = $direction === 'asc' ? 'ASC' : 'DESC';
            $query->orderByRaw("FGC_CreateDate IS NULL {$nullsOrder}")
                ->orderBy('FGC_CreateDate', $direction);

            if (Schema::hasColumn('S_Account_Information', 'ID')) {
                $query->orderBy('ID', $direction);
            }

            return;
        }

        $query->orderBy('SK_Code', $direction);
    }

    private function shouldScopeToAssignedInstitutions(): bool
    {
        $user = auth()->user();
        if (! $user || $user->hasPlatformWideViewAccess()) {
            return false;
        }

        return $user->isCoTeam() || $user->isCoachTeam() || $user->isCsTeam();
    }

    private function currentUserManagerColumn(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isCoTeam()) {
            return 'CO';
        }

        if ($user->isCoachTeam()) {
            return 'TR';
        }

        if ($user->isCsTeam()) {
            return 'CS';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveCurrentUserManagerAliases(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $aliases = collect([
            (string) ($user->name ?? ''),
            (string) ($user->email ?? ''),
        ]);

        if (Schema::hasTable('employee')) {
            $employee = Employee::query()
                ->where('EMAIL', (string) ($user->email ?? ''))
                ->first(['KOREANAME', 'ENGLISHNAME', 'EMAIL']);

            if ($employee) {
                $aliases = $aliases->merge([
                    (string) ($employee->KOREANAME ?? ''),
                    (string) ($employee->ENGLISHNAME ?? ''),
                    (string) ($employee->EMAIL ?? ''),
                ]);
            }
        }

        return $aliases
            ->map(fn (string $value): string => $this->normalizeManagerAlias($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeManagerAlias(string $value): string
    {
        return ManagerNameNormalizer::normalize($value);
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
