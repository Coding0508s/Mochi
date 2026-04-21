<?php

namespace App\Livewire;

use App\Models\AccountInformation;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\SupportRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class InstitutionList extends Component
{
    use WithPagination;
    // WithPagination: "다음 페이지", "이전 페이지" 기능을 자동으로 제공합니다.

    // ─── 검색/필터 상태 ────────────────────────────────────────────
    public string $search = '';
    // 상단 검색창에 입력된 텍스트. 빈 문자열로 시작합니다.

    public string $filterGubun = '';

    // 기관 구분 필터 (유치원 / 어린이집 / 전체)
    public string $assignmentFilter = '';
    // 담당자 배정 상태 필터: '' | assigned | unassigned | my_assigned

    public string $sortField = 'SKcode';
    // 현재 정렬 기준 컬럼

    public string $sortDirection = 'asc';
    // 정렬 방향: asc(오름차순) / desc(내림차순)

    // ─── 상세 모달 상태 ───────────────────────────────────────────────
    public bool $showDetailModal = false;

    public ?array $selectedInstitution = null;

    public array $supportHistory = [];

    public bool $showSupportDetailModal = false;

    public ?array $selectedSupportRecord = null;

    public bool $isEditingDetail = false;

    public string $editCustomerType = '';

    public string $editGsNo = '';

    public string $editDetailCo = '';

    public string $editDetailTr = '';

    public string $editDetailCs = '';

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

    public function updatingFilterGubun(): void
    {
        $this->resetPage();
    }

    public function updatingAssignmentFilter(): void
    {
        $this->resetPage();
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
        $institution = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->with('accountInfo')
            ->withCount('teachers')
            ->withCount('supportRecords')
            ->withMax('supportRecords', 'Support_Date')
            ->findOrFail($id);

        $this->selectedInstitution = [
            'id' => $institution->ID,
            'skcode' => $institution->SKcode,
            'name' => $institution->AccountName,
            'english_name' => $institution->EnglishName,
            'portal_name' => $institution->PortalAccountName,
            'gubun' => $institution->Gubun,
            'director' => $institution->Director,
            'phone' => $institution->Phone,
            'account_tel' => $institution->AccountTel,
            'address' => $institution->Address,
            'co' => $institution->accountInfo?->CO,
            'tr' => $institution->accountInfo?->TR,
            'cs' => $institution->accountInfo?->CS,
            'customer_type' => $institution->accountInfo?->Customer_Type,
            'gs_no' => $institution->GSno,
            'teacher_count' => $institution->teachers_count,
            'support_count' => $institution->support_records_count,
            'latest_support_date' => $institution->support_records_max_support_date,
        ];

        $this->isEditingDetail = false;
        $this->editCustomerType = (string) ($institution->accountInfo?->Customer_Type ?? '');
        $this->editGsNo = (string) ($institution->GSno ?? '');
        $this->editDetailCo = (string) ($institution->accountInfo?->CO ?? '');
        $this->editDetailTr = (string) ($institution->accountInfo?->TR ?? '');
        $this->editDetailCs = (string) ($institution->accountInfo?->CS ?? '');

        // 최근 10년 이력(지원/소통) 조회
        $startYear = now()->year - 9;
        $this->supportHistory = SupportRecord::query()
            ->where('SK_Code', $institution->SKcode)
            ->where(function ($q) use ($startYear) {
                $q->where('Year', '>=', $startYear)
                    ->orWhereYear('Support_Date', '>=', $startYear);
            })
            ->orderByDesc('Support_Date')
            ->orderByDesc('ID')
            ->limit(300)
            ->get()
            ->map(function (SupportRecord $record) {
                return [
                    'id' => $record->ID,
                    'support_date' => $record->Support_Date?->format('Y-m-d') ?? '-',
                    'support_time' => $record->Meet_Time ? substr((string) $record->Meet_Time, 0, 5) : '-',
                    'tr_name' => $record->TR_Name ?? '-',
                    'support_type' => $record->Support_Type ?? '-',
                    'target' => $record->Target ?? '-',
                    'issue' => $record->Issue ?? '-',
                    'to_account' => $record->TO_Account ?? '-',
                    'status' => $record->CompletedDate ? '완료' : '진행중',
                ];
            })
            ->toArray();

        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedInstitution = null;
        $this->supportHistory = [];
        $this->isEditingDetail = false;
        $this->editCustomerType = '';
        $this->editGsNo = '';
        $this->editDetailCo = '';
        $this->editDetailTr = '';
        $this->editDetailCs = '';
        $this->resetValidation();
        $this->closeSupportDetailModal();
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
        $this->resetValidation();
    }

    public function saveDetailFields(): void
    {
        if (! $this->selectedInstitution) {
            return;
        }

        $this->validate([
            'editCustomerType' => 'nullable|string|max:255',
            'editGsNo' => 'nullable|string|max:255',
            'editDetailCo' => 'nullable|string|max:255',
            'editDetailTr' => 'nullable|string|max:255',
            'editDetailCs' => 'nullable|string|max:255',
        ]);

        $institutionId = (int) ($this->selectedInstitution['id'] ?? 0);
        $skCode = (string) ($this->selectedInstitution['skcode'] ?? '');

        if ($institutionId <= 0 || blank($skCode)) {
            return;
        }

        $institution = Institution::query()->findOrFail($institutionId);
        $institution->update([
            'GSno' => trim($this->editGsNo) ?: null,
        ]);

        AccountInformation::query()->updateOrCreate(
            ['SK_Code' => $skCode],
            [
                'Account_Name' => (string) ($this->selectedInstitution['name'] ?? $institution->AccountName ?? ''),
                'Customer_Type' => trim($this->editCustomerType) ?: null,
                'CO' => trim($this->editDetailCo) ?: null,
                'TR' => trim($this->editDetailTr) ?: null,
                'CS' => trim($this->editDetailCs) ?: null,
            ]
        );

        $this->selectedInstitution['customer_type'] = trim($this->editCustomerType) ?: null;
        $this->selectedInstitution['gs_no'] = trim($this->editGsNo) ?: null;
        $this->selectedInstitution['co'] = trim($this->editDetailCo) ?: null;
        $this->selectedInstitution['tr'] = trim($this->editDetailTr) ?: null;
        $this->selectedInstitution['cs'] = trim($this->editDetailCs) ?: null;

        $this->isEditingDetail = false;
        $this->resetValidation();
        session()->flash('success', '기관 상세 정보가 저장되었습니다.');
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

        $this->selectedSupportRecord = [
            'id' => $record->ID,
            'support_date' => $record->Support_Date?->format('Y-m-d') ?? '-',
            'support_time' => $record->Meet_Time ? substr((string) $record->Meet_Time, 0, 5) : '-',
            'tr_name' => $record->TR_Name ?? '-',
            'support_type' => $record->Support_Type ?? '-',
            'target' => $record->Target ?? '-',
            'issue' => $record->Issue ?? '-',
            'to_account' => $record->TO_Account ?? '-',
            'to_depart' => $record->TO_Depart ?? '-',
            'others' => $record->Others ?? '-',
            'status' => $record->CompletedDate ? '완료' : '진행중',
            'created_date' => $record->CreatedDate?->format('Y-m-d H:i') ?? '-',
            'completed_date' => $record->CompletedDate?->format('Y-m-d H:i') ?? '-',
        ];

        $this->showSupportDetailModal = true;
    }

    public function closeSupportDetailModal(): void
    {
        $this->showSupportDetailModal = false;
        $this->selectedSupportRecord = null;
    }

    // ─── 담당자 변경 모달 열기/닫기/저장 ─────────────────────────────
    public function openManagerModal(int $id): void
    {
        $institution = Institution::query()
            ->with('accountInfo')
            ->findOrFail($id);

        $this->editingInstitutionId = $institution->ID;
        $this->editSkCode = (string) ($institution->SKcode ?? '');
        $this->editInstitutionName = (string) ($institution->AccountName ?? '');
        $this->editCo = (string) ($institution->accountInfo?->CO ?? '');
        $this->editTr = (string) ($institution->accountInfo?->TR ?? '');
        $this->editCs = (string) ($institution->accountInfo?->CS ?? '');
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

        AccountInformation::query()->updateOrCreate(
            ['SK_Code' => $this->editSkCode],
            [
                'Account_Name' => $this->editInstitutionName,
                'CO' => trim($this->editCo) ?: null,
                'TR' => trim($this->editTr) ?: null,
                'CS' => trim($this->editCs) ?: null,
            ]
        );

        // 상세 모달 열려 있으면 즉시 표시값도 갱신
        if ($this->selectedInstitution && $this->selectedInstitution['skcode'] === $this->editSkCode) {
            $this->selectedInstitution['co'] = trim($this->editCo) ?: null;
            $this->selectedInstitution['tr'] = trim($this->editTr) ?: null;
            $this->selectedInstitution['cs'] = trim($this->editCs) ?: null;
        }

        session()->flash('success', '담당자 정보가 저장되었습니다.');
        $this->closeManagerModal();
    }

    // ─── 화면에 표시할 데이터 가져오기 ───────────────────────────────
    public function render()
    {
        $hiddenInstitutionSkCodes = $this->hiddenInstitutionSkCodes();

        // 상단 요약 카드용 집계
        $allInstitutionCount = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })
            ->count();

        $assignedCoCount = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })
            ->whereHas('accountInfo', function ($q) {
                $q->whereNotNull('CO')
                    ->where('CO', '!=', '');
            })
            ->count();

        $myAssignedCoCount = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })
            ->tap(fn (Builder $query) => $this->applyCurrentUserAliasScope($query))
            ->count();

        $unassignedCoCount = max(0, $allInstitutionCount - $assignedCoCount);

        $institutions = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->search($this->search)
            // Institution 모델에 정의한 search 스코프 사용
            // 기관명, SKcode, 원장명, 주소에서 검색어를 찾습니다.
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })

            ->ofType($this->filterGubun)
            // 기관 구분(유치원/어린이집 등) 필터

            ->with('accountInfo')
            // 담당자(CO/TR/CS) 정보를 한 번에 가져옵니다. (N+1 방지)

            ->when($this->assignmentFilter === 'assigned', function ($query) {
                $query->whereHas('accountInfo', function ($q) {
                    $q->whereNotNull('CO')->where('CO', '!=', '');
                });
            })
            ->when($this->assignmentFilter === 'unassigned', function ($query) {
                $query->whereDoesntHave('accountInfo', function ($q) {
                    $q->whereNotNull('CO')->where('CO', '!=', '');
                });
            })
            ->when($this->assignmentFilter === 'my_assigned', function (Builder $query): void {
                $this->applyCurrentUserAliasScope($query);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);
        // 한 페이지에 20개씩 표시합니다.

        // 기관 구분 목록 (필터 드롭다운용)
        $gubunList = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })
            ->whereNotNull('Gubun')
            ->where('Gubun', '!=', '')
            ->distinct()
            ->pluck('Gubun');

        // 담당자 드롭다운 옵션 (기존 배정 이력 기준)
        $coManagerOptions = AccountInformation::query()
            ->whereNotNull('CO')
            ->where('CO', '!=', '')
            ->distinct()
            ->orderBy('CO')
            ->pluck('CO');

        $trManagerOptions = AccountInformation::query()
            ->whereNotNull('TR')
            ->where('TR', '!=', '')
            ->distinct()
            ->orderBy('TR')
            ->pluck('TR');

        $csManagerOptions = AccountInformation::query()
            ->whereNotNull('CS')
            ->where('CS', '!=', '')
            ->distinct()
            ->orderBy('CS')
            ->pluck('CS');

        $customerTypeOptions = AccountInformation::query()
            ->whereNotNull('Customer_Type')
            ->where('Customer_Type', '!=', '')
            ->distinct()
            ->orderBy('Customer_Type')
            ->pluck('Customer_Type');

        return view('livewire.institution-list', [
            'institutions' => $institutions,
            'gubunList' => $gubunList,
            'allInstitutionCount' => $allInstitutionCount,
            'assignedCoCount' => $assignedCoCount,
            'myAssignedCoCount' => $myAssignedCoCount,
            'unassignedCoCount' => $unassignedCoCount,
            'coManagerOptions' => $coManagerOptions,
            'trManagerOptions' => $trManagerOptions,
            'csManagerOptions' => $csManagerOptions,
            'customerTypeOptions' => $customerTypeOptions,
        ]);
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

    private function applyCoTeamInstitutionScope(Builder $query): void
    {
        if (! $this->shouldScopeToAssignedInstitutions()) {
            return;
        }

        $this->applyCurrentUserAliasScope($query);
    }

    private function applyCurrentUserAliasScope(Builder $query): void
    {
        $coAliases = $this->resolveCurrentUserCoAliases();
        if ($coAliases === []) {
            // 사용자 식별 키가 없으면 전체 노출을 막습니다.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('accountInfo', function (Builder $sub) use ($coAliases): void {
            $sub->where(function (Builder $coQuery) use ($coAliases): void {
                foreach ($coAliases as $alias) {
                    $coQuery->orWhereRaw("REPLACE(LOWER(COALESCE(CO, '')), ' ', '') = ?", [$alias]);
                }
            });
        });
    }

    private function shouldScopeToAssignedInstitutions(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->isCoTeam() && ! $user->hasFullAccess();
    }

    /**
     * @return array<int, string>
     */
    private function resolveCurrentUserCoAliases(): array
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
            ->map(function (string $value): string {
                $lower = mb_strtolower(trim($value));
                $normalized = preg_replace('/\s+/u', '', $lower);

                return is_string($normalized) ? $normalized : $lower;
            })
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
