<?php

namespace App\Livewire;

use App\Enums\SyncOrigin;
use App\Jobs\SyncInstitutionOutboundJob;
use App\Livewire\Concerns\ManagesInstitutionSupportDetailEdit;
use App\Models\AccountInformation;
use App\Models\Employee;
use App\Models\GsNumber;
use App\Models\Institution;
use App\Models\SkCodeRequest;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Support\ManagerNameNormalizer;
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
    use WithPagination;
    // WithPagination: "다음 페이지", "이전 페이지" 기능을 자동으로 제공합니다.

    // ─── 담당자 드롭다운 부서 매핑 ─────────────────────────────────
    // 상세 모달의 CO / Coach / CS 드롭다운은 아래 부서(WORKDEPT) 활성 직원만 후보로 노출합니다.
    private const DEPT_CO = 'A02'; // Consulting Team

    private const DEPT_TR = 'A05'; // Training Team (Coach)

    private const DEPT_CS = 'A03'; // Customer Support Team

    // ─── 검색/필터 상태 ────────────────────────────────────────────
    public string $search = '';
    // 상단 검색창에 입력된 텍스트. 빈 문자열로 시작합니다.

    public string $statusFilter = 'active';
    // 기관 상태 필터: active | terminated | all

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
            ->with($this->institutionEagerLoads())
            ->withCount('teachers')
            ->withCount('supportRecords')
            ->withMax('supportRecords', 'Support_Date')
            ->findOrFail($id);

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

        $this->selectedInstitution = [
            'id' => $institution->ID,
            'skcode' => $institution->SKcode,
            'name' => $institution->AccountName,
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
        $this->editDetailInstitutionName = (string) ($institution->AccountName ?? '');
        $this->editDetailEnglishName = (string) ($institution->EnglishName ?? '');
        $this->editDetailPortalName = (string) ($institution->PortalAccountName ?? '');
        $this->editDetailPortalCampusId = (string) ($institution->PortalCampusID ?? '');
        $this->editDetailAccountNo = (string) ($institution->AccountNo ?? '');
        $this->editDetailGubun = (string) ($institution->Gubun ?? '');
        $this->editDetailDirector = (string) ($institution->Director ?? '');
        $this->editDetailPhone = (string) ($institution->Phone ?? '');
        $this->editDetailAccountTel = (string) ($institution->AccountTel ?? '');
        $this->editDetailAddress = (string) ($institution->Address ?? '');

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
                    'support_time' => $this->formatSupportMeetTime($record->Meet_Time),
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

        DB::transaction(function () use ($institution, $oldSk, $newSk, $accountName, $trimmedGs): void {
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

        return Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
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
            'status' => $record->CompletedDate ? '완료' : '진행중',
            'created_date' => $record->CreatedDate?->format('Y-m-d H:i') ?? '-',
            'completed_date' => $record->CompletedDate?->format('Y-m-d H:i') ?? '-',
            'can_edit' => $this->resolveSupportRecordCanEdit($record),
        ];
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

        AccountInformation::query()->updateOrCreate(
            ['SK_Code' => $this->editSkCode],
            [
                'Account_Name' => $this->editInstitutionName,
                'CO' => $co,
                'TR' => $tr,
                'CS' => $cs,
            ]
        );

        $this->reverseSyncToSkCodeRequest($this->editSkCode, [
            'institution_name' => $this->editInstitutionName,
            'co' => $co,
            'tr' => $tr,
            'cs' => $cs,
        ]);

        DB::afterCommit(function (): void {
            SyncInstitutionOutboundJob::dispatchIf(
                (bool) config('services.institution_outbound.enabled'),
                $this->editSkCode,
                SyncOrigin::Local
            );
        });

        // 상세 모달 열려 있으면 즉시 표시값도 갱신
        if ($this->selectedInstitution && $this->selectedInstitution['skcode'] === $this->editSkCode) {
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

        // 상단 요약 카드용 집계
        $allInstitutionCount = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->tap(fn (Builder $query) => $this->applyStatusFilter($query))
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })
            ->count();

        $assignedCoCount = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->tap(fn (Builder $query) => $this->applyStatusFilter($query))
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
            ->tap(fn (Builder $query) => $this->applyStatusFilter($query))
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })
            ->tap(fn (Builder $query) => $this->applyCurrentUserAliasScope($query))
            ->count();

        $unassignedCoCount = max(0, $allInstitutionCount - $assignedCoCount);

        $institutions = Institution::query()
            ->tap(fn (Builder $query) => $this->applyCoTeamInstitutionScope($query))
            ->tap(fn (Builder $query) => $this->applyStatusFilter($query))
            ->search($this->search)
            // Institution 모델에 정의한 search 스코프 사용
            // 기관명, SKcode, 원장명, 주소에서 검색어를 찾습니다.
            ->when($hiddenInstitutionSkCodes !== [], function ($query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SKcode', $hiddenInstitutionSkCodes);
            })

            ->with($this->institutionEagerLoads())
            // 담당자(CO/TR/CS) 및 S_GSNumber (테이블 있을 때만, N+1 방지)

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
        //  - Coach -> Training Team   (A05)
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

        $sqlNormalizedCo = ManagerNameNormalizer::sqlColumnExpression('CO');

        $query->whereHas('accountInfo', function (Builder $sub) use ($coAliases, $sqlNormalizedCo): void {
            $sub->where(function (Builder $coQuery) use ($coAliases, $sqlNormalizedCo): void {
                foreach ($coAliases as $alias) {
                    $coQuery->orWhereRaw("{$sqlNormalizedCo} = ?", [$alias]);
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
