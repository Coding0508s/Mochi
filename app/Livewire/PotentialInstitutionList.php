<?php

namespace App\Livewire;

use App\Actions\CreatePotentialMeetingDetail;
use App\Actions\DeletePotentialMeetingDetail;
use App\Actions\PromotePotentialInstitutionToMaster;
use App\Enums\SyncOrigin;
use App\Jobs\SyncInstitutionOutboundJob;
use App\Livewire\Concerns\ManagesPotentialInstitutionDetailEdit;
use App\Livewire\Concerns\ManagesPotentialMeetingDetailEdit;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\Employee;
use App\Models\SkCodeRequest;
use App\Models\SupportRecord;
use App\Models\User;
use App\Support\ManagerNameNormalizer;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class PotentialInstitutionList extends Component
{
    use ManagesPotentialInstitutionDetailEdit;
    use ManagesPotentialMeetingDetailEdit;
    use WithPagination;

    public string $search = '';

    public string $filterYear = '';

    public string $filterManager = '';

    public string $filterType = '';

    public string $filterRegion = '';

    /** 소개경로(`Connected`). 빈 값=전체, __empty__=미입력 */
    public string $filterIntroductionPath = '';

    /** 계약가능성: 빈 값=전체, contract=계약완료, A–D, none=미지정 */
    public string $filterContractPossibility = '';

    public string $summaryFilter = 'all'; // all | new | terminated

    // 신규 등록 모달 상태
    public bool $showCreateModal = false;

    public string $newManager = '';

    public string $newConsultingType = '';

    public string $newConnected = '';

    public string $newMeetingDate = '';

    public string $newMeetingTime = '';

    public string $newMeetingTimeEnd = '';

    public string $newType = '';

    public string $newAccountName = '';

    public string $newDirector = '';

    public string $newPhone = '';

    public string $newAddress = '';

    public string $newPossibility = '';

    public string $newDescription = '';

    public string $newLS = '';

    public string $newGSK = '';

    public string $newGSE = '';

    public string $newApproaching = '';

    public string $newPresenting = '';

    public string $newConsultingCount = '';

    public string $newClosing = '';

    public string $newDroppedOut = '';

    /** 신규 등록 모달에서 기관 지원 보고서(S_SupportInfo_Account)를 함께 저장 */
    public bool $newIncludeSupportReport = false;

    public string $newSupportReportDate = '';

    public string $newSupportReportTime = '';

    public string $newSupportReportType = '전화';

    public string $newSupportReportTarget = '';

    public string $newSupportReportToAccount = '';

    public string $newSupportReportToDepart = '';

    public bool $newSupportReportCompleted = false;

    public string $newSupportReportTrName = '';

    // 상세 모달 상태
    public bool $showDetailModal = false;

    public ?array $selectedTarget = null;

    public array $detailMeetings = [];

    public array $detailSupportRecords = [];

    public bool $showMeetingDetailModal = false;

    public ?array $selectedMeeting = null;

    public bool $showSupportDetailModal = false;

    public ?array $selectedSupportRecord = null;

    /** 상세 모달 계약여부 편집: '0'=미계약, '1'=계약 */
    public string $detailModalContract = '0';

    // 계약완료 미니 모달용
    public bool $showContractModal = false;

    public ?int $pendingContractId = null;

    public string $contractSkCode = '';

    public string $pendingContractName = '';

    // 상세 모달 계약 변경 확인 모달용
    public bool $showContractChangeConfirmModal = false;

    public ?bool $pendingContractChange = null;

    public string $pendingContractChangeName = '';

    // 상세 모달 SK CODE 입력용
    public string $detailModalSkCode = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatingFilterManager(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRegion(): void
    {
        $this->resetPage();
    }

    public function updatingSummaryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFilterIntroductionPath(): void
    {
        $this->resetPage();
    }

    public function updatingFilterContractPossibility(): void
    {
        $this->resetPage();
    }

    public function openContractModal(int $id): void
    {
        $target = CoNewTarget::query()->findOrFail($id);

        if (! $this->ensureTargetManageable($target)) {
            return;
        }

        $this->pendingContractId = $id;
        $this->pendingContractName = (string) ($target->AccountName ?? '');
        $this->contractSkCode = '';
        $this->showContractModal = true;
    }

    public function closeContractModal(): void
    {
        $this->showContractModal = false;
        $this->pendingContractId = null;
        $this->contractSkCode = '';
        $this->pendingContractName = '';
    }

    public function markContractComplete(int $id): void
    {
        $target = CoNewTarget::query()->findOrFail($id);

        if (! $this->ensureTargetManageable($target)) {
            return;
        }

        if ($target->IsContract) {
            return;
        }

        $inputSk = trim($this->contractSkCode);
        if ($inputSk !== '') {
            $target->AccountCode = $inputSk;
        }

        $this->applyContractState($target, true);
        $this->closeContractModal();

        session()->flash('success', '계약완료 처리되었습니다.');
    }

    /**
     * 상세 모달에서 계약여부 select 변경 시 호출 (`wire:change`).
     */
    public function commitDetailContract(): void
    {
        $this->requestContractChange();
    }

    public function requestContractChange(): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        $id = (int) ($this->selectedTarget['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $contracted = $this->detailModalContract === '1';
        $target = CoNewTarget::query()->findOrFail($id);

        if (! $this->ensureTargetManageable($target)) {
            return;
        }

        if ((bool) $target->IsContract === $contracted) {
            return;
        }

        $this->pendingContractChange = $contracted;
        $this->pendingContractChangeName = (string) ($target->AccountName ?? '');
        $this->showContractChangeConfirmModal = true;
    }

    public function confirmContractChange(): void
    {
        if ($this->selectedTarget === null || $this->pendingContractChange === null) {
            $this->cancelContractChange();

            return;
        }

        $id = (int) ($this->selectedTarget['id'] ?? 0);
        if ($id <= 0) {
            $this->cancelContractChange();

            return;
        }

        $contracted = $this->pendingContractChange;
        $target = CoNewTarget::query()->findOrFail($id);

        if (! $this->ensureTargetManageable($target)) {
            $this->cancelContractChange();

            return;
        }

        if ((bool) $target->IsContract === $contracted) {
            $this->cancelContractChange();

            return;
        }

        if ($contracted) {
            $inputSk = trim($this->detailModalSkCode);
            if ($inputSk !== '') {
                $target->AccountCode = $inputSk;
            }
        }

        $this->applyContractState($target, $contracted);
        $target->refresh();
        $this->syncSelectedTargetContractFields($contracted);
        if ($contracted && $this->selectedTarget !== null && (int) ($this->selectedTarget['id'] ?? 0) === $id) {
            $this->selectedTarget['account_code'] = $target->AccountCode;
        }
        $this->resetContractChangeConfirmation();
        session()->flash('success', $contracted ? '계약으로 변경되었습니다.' : '미계약으로 변경되었습니다.');
    }

    public function cancelContractChange(): void
    {
        if ($this->selectedTarget !== null) {
            $this->detailModalContract = ($this->selectedTarget['is_contract'] ?? false) ? '1' : '0';
        }

        $this->resetContractChangeConfirmation();
    }

    private function resetContractChangeConfirmation(): void
    {
        $this->showContractChangeConfirmModal = false;
        $this->pendingContractChange = null;
        $this->pendingContractChangeName = '';
    }

    private function ensureTargetManageable(CoNewTarget $target): bool
    {
        $user = auth()->user();
        if ($user !== null && $target->isManagedBy($user)) {
            return true;
        }

        $this->addError('authorization', '본인이 등록한 잠재기관만 관리할 수 있습니다.');

        return false;
    }

    private function applyContractState(CoNewTarget $target, bool $contracted): void
    {
        DB::transaction(function () use ($target, $contracted): void {
            if ($contracted) {
                $target->update([
                    'IsContract' => true,
                    'ContractedDate' => now()->toDateString(),
                ]);

                $sk = app(PromotePotentialInstitutionToMaster::class)->execute($target);

                // LEAD- 임시 코드가 발급된 경우에만 브릿지 테이블에 pending 레코드 생성.
                // 실제 SK를 직접 입력한 경우엔 외부 검증이 불필요하므로 건너뛴다.
                if (str_starts_with($sk, 'LEAD-')) {
                    SkCodeRequest::create([
                        'co_new_target_id' => (int) $target->ID,
                        'institution_name' => (string) ($target->AccountName ?? ''),
                        'temp_sk_code' => $sk,
                        'status' => 'pending',
                        'requested_at' => now(),
                    ]);
                }

                DB::afterCommit(function () use ($sk): void {
                    SyncInstitutionOutboundJob::dispatchIf(
                        (bool) config('services.institution_outbound.enabled'),
                        $sk,
                        SyncOrigin::Local
                    );
                });
            } else {
                $target->update([
                    'IsContract' => false,
                    'ContractedDate' => null,
                ]);

                $sk = trim((string) ($target->AccountCode ?? ''));
                if ($sk !== '' && Schema::hasTable('institution_visibility_overrides')) {
                    DB::table('institution_visibility_overrides')->updateOrInsert(
                        ['sk_code' => $sk],
                        [
                            'hidden_reason' => 'uncontracted',
                            'hidden_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });
    }

    private function syncSelectedTargetContractFields(bool $contracted): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        $this->selectedTarget['is_contract'] = $contracted;
        $this->selectedTarget['contracted_date'] = $contracted
            ? now()->format('Y-m-d')
            : '-';
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function updatedNewIncludeSupportReport(mixed $value): void
    {
        if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ($this->newSupportReportDate === '' && $this->newMeetingDate !== '') {
            $this->newSupportReportDate = $this->newMeetingDate;
        }

        if ($this->newSupportReportTime === '' && $this->newMeetingTime !== '') {
            $this->newSupportReportTime = $this->newMeetingTime;
        }

        if ($this->newSupportReportToAccount === '') {
            $this->newSupportReportToAccount = (string) config('support_report_defaults.to_account_template', '');
        }

        if ($this->newSupportReportToDepart === '') {
            $this->newSupportReportToDepart = (string) config('support_report_defaults.to_depart_template', '');
        }
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function saveNewTarget(): void
    {
        $validated = $this->validate();
        $meetingDate = Carbon::parse($validated['newMeetingDate']);
        $creatorId = auth()->id();
        $hasCreatedByColumn = Schema::hasColumn('S_CO_NewTarget', 'created_by');

        try {
            DB::transaction(function () use ($validated, $meetingDate, $creatorId, $hasCreatedByColumn): void {
                $ls = $this->toNonNegativeInt($validated['newLS'] ?? null);
                $gsK = $this->toNonNegativeInt($validated['newGSK'] ?? null);
                $gsE = $this->toNonNegativeInt($validated['newGSE'] ?? null);

                $targetPayload = [
                    'Year' => (int) $meetingDate->format('Y'),
                    'CreatedDate' => $meetingDate->format('Y-m-d'),
                    'AccountManager' => $validated['newManager'] ?: null,
                    // 신규(미계약) 등록 단계에서는 SK를 발급/반영하지 않습니다.
                    'AccountCode' => null,
                    'AccountName' => $validated['newAccountName'],
                    'Address' => $validated['newAddress'] ?: null,
                    'Director' => $validated['newDirector'] ?: null,
                    'Phone' => $validated['newPhone'] ?: null,
                    'Connected' => $validated['newConnected'] ?: null,
                    'Type' => $validated['newType'],
                    'Gubun' => $validated['newConsultingType'],
                    'LS' => $ls,
                    'GS_K' => $gsK,
                    'GS_E' => $gsE,
                    'Total' => $ls + $gsK + $gsE,
                    'Approaching' => $this->toNonNegativeInt($validated['newApproaching'] ?? null),
                    'Presenting' => $this->toNonNegativeInt($validated['newPresenting'] ?? null),
                    'Consulting' => $this->toNonNegativeInt($validated['newConsultingCount'] ?? null),
                    'Closing' => $this->toNonNegativeInt($validated['newClosing'] ?? null),
                    'DroppedOut' => $this->toNonNegativeInt($validated['newDroppedOut'] ?? null),
                    'IsContract' => false,
                    'ContractedDate' => null,
                    'Possibility' => $validated['newPossibility'] ?: null,
                ];

                if ($hasCreatedByColumn) {
                    $targetPayload['created_by'] = $creatorId;
                }

                $target = CoNewTarget::query()->create($targetPayload);

                if ($hasCreatedByColumn) {
                    $target->created_by = $creatorId;
                }

                app(CreatePotentialMeetingDetail::class)($target, [
                    'meeting_date' => $meetingDate->format('Y-m-d'),
                    'meeting_time' => $validated['newMeetingTime'] ?: null,
                    'meeting_time_end' => $validated['newMeetingTimeEnd'] ?: null,
                    'description' => $validated['newDescription'] ?: null,
                    'consulting_type' => $validated['newConsultingType'],
                    'possibility' => $validated['newPossibility'] ?: null,
                    'account_manager' => $validated['newManager'] ?: null,
                ]);

                if (config('potential_institutions.show_support_report_ui')
                    && ! empty($validated['newIncludeSupportReport'])
                    && Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
                    $supportDate = Carbon::parse($validated['newSupportReportDate']);
                    $timeRaw = trim((string) ($validated['newSupportReportTime'] ?? ''));
                    $meetTimeSuffix = $timeRaw !== '' ? $timeRaw.':00' : '00:00:00';

                    $supportPayload = [
                        'Year' => (int) $supportDate->format('Y'),
                        'SK_Code' => null,
                        'potential_target_id' => (int) $target->ID,
                        'Account_Name' => $validated['newAccountName'],
                        'TR_Name' => $validated['newSupportReportTrName'] ?: null,
                        'Support_Date' => $supportDate->format('Y-m-d'),
                        'Meet_Time' => $meetTimeSuffix,
                        'Support_Type' => $validated['newSupportReportType'],
                        'Target' => $validated['newSupportReportTarget'] ?: null,
                        'Issue' => null,
                        'TO_Account' => $validated['newSupportReportToAccount'] ?: null,
                        'TO_Depart' => $validated['newSupportReportToDepart'] ?: null,
                        'Status' => ! empty($validated['newSupportReportCompleted']) ? '완료' : '진행중',
                        'CompletedDate' => ! empty($validated['newSupportReportCompleted']) ? now() : null,
                        'CreatedDate' => now(),
                    ];

                    foreach (array_keys($supportPayload) as $column) {
                        if (! Schema::hasColumn('S_SupportInfo_Account', $column)) {
                            unset($supportPayload[$column]);
                        }
                    }

                    SupportRecord::query()->create($supportPayload);
                }
            });
        } catch (Throwable $e) {
            report($e);
            $message = app()->hasDebugModeEnabled()
                ? '저장 중 오류가 발생했습니다: '.$e->getMessage()
                : '저장 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.';

            $this->addError('createForm', $message);

            return;
        }

        $this->closeCreateModal();
        $this->resetCreateForm();
        $this->resetPage();
        session()->flash('success', '잠재 기관이 등록되었습니다. 계약 처리 시 SK코드가 발급됩니다.');
    }

    protected function rules(): array
    {
        return [
            'newManager' => ['nullable', 'string', 'max:100'],
            'newConsultingType' => ['required', 'string', 'max:100'],
            'newConnected' => ['nullable', 'string', 'max:100'],
            'newMeetingDate' => ['required', 'date'],
            'newMeetingTime' => ['nullable', 'date_format:H:i'],
            'newMeetingTimeEnd' => ['nullable', 'date_format:H:i'],
            'newType' => ['required', 'string', 'max:100'],
            'newAccountName' => ['required', 'string', 'max:150'],
            'newDirector' => ['nullable', 'string', 'max:100'],
            'newPhone' => ['nullable', 'string', 'max:50'],
            'newAddress' => ['nullable', 'string', 'max:255'],
            'newPossibility' => ['nullable', 'string', 'max:20'],
            'newDescription' => ['nullable', 'string', 'max:2000'],
            'newLS' => ['nullable', 'integer', 'min:0'],
            'newGSK' => ['nullable', 'integer', 'min:0'],
            'newGSE' => ['nullable', 'integer', 'min:0'],
            'newApproaching' => ['nullable', 'integer', 'min:0'],
            'newPresenting' => ['nullable', 'integer', 'min:0'],
            'newConsultingCount' => ['nullable', 'integer', 'min:0'],
            'newClosing' => ['nullable', 'integer', 'min:0'],
            'newDroppedOut' => ['nullable', 'integer', 'min:0'],
            'newIncludeSupportReport' => ['boolean'],
            'newSupportReportDate' => [
                Rule::requiredIf(fn (): bool => $this->newIncludeSupportReport),
                'nullable',
                'date',
            ],
            'newSupportReportTime' => [
                Rule::requiredIf(fn (): bool => $this->newIncludeSupportReport),
                'nullable',
                'date_format:H:i',
            ],
            'newSupportReportType' => [
                Rule::requiredIf(fn (): bool => $this->newIncludeSupportReport),
                'nullable',
                'string',
                'max:100',
            ],
            'newSupportReportTarget' => ['nullable', 'string', 'max:255'],
            'newSupportReportToAccount' => ['nullable', 'string', 'max:20000'],
            'newSupportReportToDepart' => ['nullable', 'string', 'max:20000'],
            'newSupportReportCompleted' => ['boolean'],
            'newSupportReportTrName' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'newConsultingType.required' => '컨설팅타입을 입력해 주세요.',
            'newMeetingDate.required' => '미팅일자를 입력해 주세요.',
            'newMeetingDate.date' => '미팅일자 형식이 올바르지 않습니다.',
            'newMeetingTime.date_format' => '미팅 시작시간 형식이 올바르지 않습니다.',
            'newMeetingTimeEnd.date_format' => '미팅 종료시간 형식이 올바르지 않습니다.',
            'newType.required' => '신규구분을 선택해 주세요.',
            'newAccountName.required' => '기관명을 입력해 주세요.',
            'newLS.integer' => 'LittleSEED는 숫자만 입력해 주세요.',
            'newGSK.integer' => 'GrapeSEED(유)는 숫자만 입력해 주세요.',
            'newGSE.integer' => 'GrapeSEED(초)는 숫자만 입력해 주세요.',
            'newApproaching.integer' => '관계형성 횟수는 숫자만 입력해 주세요.',
            'newPresenting.integer' => '제품소개 횟수는 숫자만 입력해 주세요.',
            'newConsultingCount.integer' => '상담/조정 횟수는 숫자만 입력해 주세요.',
            'newClosing.integer' => '도입제안 횟수는 숫자만 입력해 주세요.',
            'newDroppedOut.integer' => '도입취소 횟수는 숫자만 입력해 주세요.',
            '*.min' => '숫자는 0 이상이어야 합니다.',
            'newSupportReportDate.required' => '지원 보고서를 함께 등록할 때는 지원 날짜를 입력해 주세요.',
            'newSupportReportDate.date' => '지원 날짜 형식이 올바르지 않습니다.',
            'newSupportReportTime.required' => '지원 보고서를 함께 등록할 때는 지원 시간을 입력해 주세요.',
            'newSupportReportTime.date_format' => '지원 시간 형식이 올바르지 않습니다.',
            'newSupportReportType.required' => '지원 보고서를 함께 등록할 때는 지원 유형을 입력해 주세요.',
        ];
    }

    private function resetCreateForm(): void
    {
        $this->newManager = (string) (auth()->user()?->nameForCoReports() ?? '');
        $this->newConsultingType = '';
        $this->newConnected = '';
        $this->newMeetingDate = '';
        $this->newMeetingTime = '';
        $this->newMeetingTimeEnd = '';
        $this->newType = '';
        $this->newAccountName = '';
        $this->newDirector = '';
        $this->newPhone = '';
        $this->newAddress = '';
        $this->newPossibility = '';
        $this->newDescription = '';
        $this->newLS = '';
        $this->newGSK = '';
        $this->newGSE = '';
        $this->newApproaching = '';
        $this->newPresenting = '';
        $this->newConsultingCount = '';
        $this->newClosing = '';
        $this->newDroppedOut = '';
        $this->newIncludeSupportReport = false;
        $this->newSupportReportDate = '';
        $this->newSupportReportTime = '';
        $this->newSupportReportType = '전화';
        $this->newSupportReportTarget = '';
        $this->newSupportReportToAccount = '';
        $this->newSupportReportToDepart = '';
        $this->newSupportReportCompleted = false;
        $this->newSupportReportTrName = (string) (auth()->user()?->nameForCoReports() ?? '');
    }

    protected function toNonNegativeInt(mixed $value): int
    {
        $intValue = (int) $value;

        return max(0, $intValue);
    }

    protected function reloadDetailModalAfterTargetUpdate(CoNewTarget $target): void
    {
        $this->openDetailModal((int) $target->ID);
    }

    // 행 클릭 시 상세 모달 오픈
    public function openDetailModal(int $id): void
    {
        $this->resetDetailEditState();
        $this->resetMeetingDetailEditState();

        $target = CoNewTarget::query()
            ->findOrFail($id);

        $user = auth()->user();

        $meetingCount = CoNewTargetDetail::query()
            ->ofAccount((string) ($target->AccountName ?? ''))
            ->when(filled($target->AccountManager), function ($q) use ($target) {
                $q->where('AccountManager', $target->AccountManager);
            })
            ->count();

        $this->selectedTarget = [
            'id' => $target->ID,
            'year' => $target->Year,
            'created_date' => $target->CreatedDate?->format('Y-m-d') ?? '-',
            'account_manager' => $target->AccountManager,
            'account_code' => $target->AccountCode,
            'account_name' => $target->AccountName,
            'address' => $target->Address,
            'director' => $target->Director,
            'phone' => $target->Phone,
            'type' => $target->Type,
            'gubun' => $target->Gubun,
            'possibility' => $target->Possibility,
            'connected' => $target->Connected,
            'ls' => $target->LS,
            'gs_k' => $target->GS_K,
            'gs_e' => $target->GS_E,
            'total' => $target->studentTotal(),
            'approaching' => $target->Approaching,
            'presenting' => $target->Presenting,
            'consulting' => $target->Consulting,
            'closing' => $target->Closing,
            'dropped_out' => $target->DroppedOut,
            'is_contract' => (bool) ($target->IsContract ?? false),
            'contracted_date' => $target->ContractedDate?->format('Y-m-d') ?? '-',
            'meeting_count' => $meetingCount,
            'can_manage' => $user !== null && $target->isManagedBy($user),
        ];

        $this->detailMeetings = CoNewTargetDetail::query()
            ->ofAccount((string) ($target->AccountName ?? ''))
            ->when(filled($target->AccountManager), function ($q) use ($target) {
                $q->where('AccountManager', $target->AccountManager);
            })
            ->orderByDesc('MeetingDate')
            ->orderByDesc('ID')
            ->limit(100)
            ->get()
            ->map(function (CoNewTargetDetail $detail) {
                return [
                    'id' => $detail->ID,
                    'year' => $detail->Year,
                    'account_name' => $detail->AccountName ?? '-',
                    'meeting_date' => $detail->MeetingDate?->format('Y-m-d') ?? '-',
                    'meeting_time' => $detail->MeetingTime ? substr((string) $detail->MeetingTime, 0, 5) : '-',
                    'meeting_time_end' => $detail->MeetingTime_End ? substr((string) $detail->MeetingTime_End, 0, 5) : '-',
                    'account_manager' => $detail->AccountManager ?? '-',
                    'consulting_type' => $detail->ConsultingType ?? '-',
                    'possibility' => $detail->Possibility ?? '-',
                    'description' => $detail->Description ?? '-',
                ];
            })
            ->toArray();

        $skForSupport = trim((string) ($target->AccountCode ?? ''));

        $supportBase = SupportRecord::query();
        if (Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
            $supportBase->where(function ($query) use ($target, $skForSupport): void {
                $query->where('potential_target_id', (int) $target->ID);
                if ($skForSupport !== '') {
                    $query->orWhere('SK_Code', $skForSupport);
                }
            });
        } elseif ($skForSupport !== '') {
            $supportBase->where('SK_Code', $skForSupport);
        } else {
            $supportBase->whereRaw('1 = 0');
        }

        $this->detailSupportRecords = $supportBase
            ->orderByDesc('Support_Date')
            ->orderByDesc('ID')
            ->limit(50)
            ->get()
            ->map(function (SupportRecord $record): array {
                return [
                    'id' => $record->ID,
                    'support_date' => $record->Support_Date?->format('Y-m-d') ?? '-',
                    'meet_time' => $this->formatSupportTime($record->Meet_Time),
                    'tr_name' => $record->TR_Name ?? '-',
                    'support_type' => $record->Support_Type ?? '-',
                    'target' => $record->Target ?? '-',
                    'to_account' => $record->TO_Account ?? '-',
                    'status' => $record->Status ?? '-',
                    'completed' => ! is_null($record->CompletedDate),
                ];
            })
            ->toArray();

        $this->detailModalContract = ($target->IsContract ?? false) ? '1' : '0';
        $this->detailModalSkCode = trim((string) ($target->AccountCode ?? ''));

        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedTarget = null;
        $this->detailMeetings = [];
        $this->detailSupportRecords = [];
        $this->detailModalContract = '0';
        $this->detailModalSkCode = '';
        $this->resetDetailEditState();
        $this->resetMeetingDetailEditState();
        $this->showMeetingDetailModal = false;
        $this->selectedMeeting = null;
        $this->showSupportDetailModal = false;
        $this->selectedSupportRecord = null;
    }

    private function formatSupportTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $stringValue = trim((string) $value);
        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '-';
    }

    public function openMeetingDetailModal(int $meetingId): void
    {
        $this->resetMeetingDetailEditState();

        $meeting = collect($this->detailMeetings)->firstWhere('id', $meetingId);

        if (! $meeting) {
            return;
        }

        $this->selectedMeeting = $meeting;
        $this->showMeetingDetailModal = true;
    }

    public function closeMeetingDetailModal(): void
    {
        $this->resetMeetingDetailEditState();
        $this->showMeetingDetailModal = false;
        $this->selectedMeeting = null;
    }

    #[On('potential-meeting-saved')]
    public function refreshDetailAfterMeeting(int $targetId): void
    {
        if (! $this->showDetailModal) {
            return;
        }

        if ((int) ($this->selectedTarget['id'] ?? 0) !== $targetId) {
            return;
        }

        $this->openDetailModal($targetId);
    }

    public function deleteMeetingDetail(int $detailId): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        $targetId = (int) ($this->selectedTarget['id'] ?? 0);
        if ($targetId <= 0) {
            return;
        }

        $target = CoNewTarget::query()->find($targetId);
        if (! $target) {
            return;
        }

        try {
            app(DeletePotentialMeetingDetail::class)($target, $detailId);
        } catch (AuthorizationException $e) {
            $this->addError('deleteMeeting', $e->getMessage());

            return;
        } catch (ModelNotFoundException $e) {
            report($e);
            $this->addError('deleteMeeting', '삭제할 미팅 이력을 찾을 수 없습니다.');

            return;
        }

        $this->closeMeetingDetailModal();
        $this->openDetailModal($targetId);
        session()->flash('success', '미팅/컨설팅 이력을 삭제했습니다.');
    }

    protected function reloadMeetingDetailAfterUpdate(CoNewTargetDetail $detail): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        $targetId = (int) ($this->selectedTarget['id'] ?? 0);
        if ($targetId <= 0) {
            return;
        }

        $this->openDetailModal($targetId);

        $updatedMeeting = collect($this->detailMeetings)->firstWhere('id', (int) $detail->ID);
        if (! $updatedMeeting) {
            return;
        }

        $this->selectedMeeting = $updatedMeeting;
        $this->showMeetingDetailModal = true;
    }

    public function openSupportDetailModal(int $supportRecordId): void
    {
        $record = collect($this->detailSupportRecords)->firstWhere('id', $supportRecordId);

        if (! $record) {
            return;
        }

        $this->selectedSupportRecord = $record;
        $this->showSupportDetailModal = true;
    }

    public function closeSupportDetailModal(): void
    {
        $this->showSupportDetailModal = false;
        $this->selectedSupportRecord = null;
    }

    public function render()
    {
        $query = CoNewTarget::query()
            ->keyword($this->search);

        if (filled($this->filterYear)) {
            $query->where('Year', (int) $this->filterYear);
        }

        if (filled($this->filterManager)) {
            $normalizedManager = ManagerNameNormalizer::normalize($this->filterManager);
            if ($normalizedManager !== '') {
                $normalizedAccountManager = ManagerNameNormalizer::sqlColumnExpression('AccountManager');
                $query->whereRaw("{$normalizedAccountManager} = ?", [$normalizedManager]);
            } else {
                $query->where('AccountManager', $this->filterManager);
            }
        }

        if (filled($this->filterType)) {
            $type = trim($this->filterType);
            $query->where(function ($q) use ($type) {
                $q->where('Type', 'like', "%{$type}%")
                    ->orWhere('Gubun', 'like', "%{$type}%");
            });
        }

        if (filled($this->filterRegion)) {
            $normalizedRegion = preg_replace('/\s+/u', '', (string) $this->filterRegion) ?? '';
            if ($normalizedRegion !== '') {
                $query->whereRaw("REPLACE(Address, ' ', '') like ?", ["%{$normalizedRegion}%"]);
            }
        }

        if (filled($this->filterIntroductionPath)) {
            if ($this->filterIntroductionPath === '__empty__') {
                $query->where(function ($q): void {
                    $q->whereNull('Connected')
                        ->orWhere('Connected', '');
                });
            } else {
                $query->where('Connected', $this->filterIntroductionPath);
            }
        }

        if (filled($this->filterContractPossibility)) {
            match ($this->filterContractPossibility) {
                'contract' => $query->where('IsContract', true),
                'none' => $query->where('IsContract', false)->where(function ($q): void {
                    $q->whereNull('Possibility')
                        ->orWhere('Possibility', '');
                }),
                'A', 'B', 'C', 'D' => $query->where('IsContract', false)
                    ->where('Possibility', $this->filterContractPossibility),
                default => null,
            };
        }

        if ($this->summaryFilter === 'new') {
            $query->where(function ($q) {
                $q->where('Type', 'like', '%신규%')
                    ->orWhere('Gubun', 'like', '%신규%');
            });
        } elseif ($this->summaryFilter === 'terminated') {
            $query->where(function ($q) {
                $q->where('Type', 'like', '%해지%')
                    ->orWhere('Gubun', 'like', '%해지%')
                    ->orWhere('Possibility', 'like', '%해지%');
            });
        }

        $this->applyManageableTargetScope($query);

        $totalCount = (clone $query)->count();

        $targets = $query
            ->orderByDesc('CreatedDate')
            ->orderByDesc('ID')
            ->paginate(15);

        $meetingCountByAccount = CoNewTargetDetail::query()
            ->whereIn('AccountName', $targets->pluck('AccountName')->filter()->unique()->values())
            ->selectRaw('AccountName, COUNT(*) as cnt')
            ->groupBy('AccountName')
            ->pluck('cnt', 'AccountName')
            ->toArray();

        $yearList = $this->manageableTargetQuery()
            ->whereNotNull('Year')
            ->distinct()
            ->orderByDesc('Year')
            ->pluck('Year');

        $masterManagerOptions = $this->managerMasterOptions();

        $managerList = $this->manageableTargetQuery()
            ->whereNotNull('AccountManager')
            ->where('AccountManager', '!=', '')
            ->distinct()
            ->pluck('AccountManager')
            ->map(fn (string $name): string => $this->alignManagerLabelToMasterOption($name, $masterManagerOptions))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->sort()
            ->values();

        $typeList = $this->manageableTargetQuery()
            ->whereNotNull('Type')
            ->where('Type', '!=', '')
            ->distinct()
            ->orderBy('Type')
            ->pluck('Type');

        $introductionPathList = $this->manageableTargetQuery()
            ->whereNotNull('Connected')
            ->where('Connected', '!=', '')
            ->distinct()
            ->orderBy('Connected')
            ->pluck('Connected');

        $allCount = $this->manageableTargetQuery()->count();

        $newCount = $this->manageableTargetQuery()
            ->where(function ($q) {
                $q->where('Type', 'like', '%신규%')
                    ->orWhere('Gubun', 'like', '%신규%');
            })
            ->count();

        $terminatedCount = $this->manageableTargetQuery()
            ->where(function ($q) {
                $q->where('Type', 'like', '%해지%')
                    ->orWhere('Gubun', 'like', '%해지%')
                    ->orWhere('Possibility', 'like', '%해지%');
            })
            ->count();

        return view('livewire.potential-institution-list', [
            'targets' => $targets,
            'meetingCountByAccount' => $meetingCountByAccount,
            'yearList' => $yearList,
            'managerList' => $managerList,
            'typeList' => $typeList,
            'introductionPathList' => $introductionPathList,
            'totalCount' => $totalCount,
            'allCount' => $allCount,
            'newCount' => $newCount,
            'terminatedCount' => $terminatedCount,
        ]);
    }

    private function applyManageableTargetScope(Builder $query): void
    {
        $userId = auth()->id();
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->hasFullAccess()) {
            return;
        }

        $normalizedUserName = ManagerNameNormalizer::normalize($user->nameForCoReports());
        $normalizedAccountManager = ManagerNameNormalizer::sqlColumnExpression('AccountManager');

        $query->where(function (Builder $scope) use ($user, $normalizedUserName, $normalizedAccountManager): void {
            $scope->where('created_by', $user->id);

            if ($normalizedUserName !== '') {
                $scope->orWhere(function (Builder $legacyScope) use ($normalizedUserName, $normalizedAccountManager): void {
                    $legacyScope->whereNull('created_by')
                        ->whereRaw("{$normalizedAccountManager} = ?", [$normalizedUserName]);
                });
            }
        });
    }

    private function manageableTargetQuery(): Builder
    {
        $query = CoNewTarget::query();
        $this->applyManageableTargetScope($query);

        return $query;
    }

    /**
     * 잠재기관 담당자 필터 옵션에 사용할 직원 마스터 이름 목록입니다.
     *
     * - employee 테이블이 있으면 STATUS=1 활성 직원의 영문명(없으면 한글명) 사용
     * - 테이블이 없으면 빈 컬렉션 반환(레거시/테스트 환경 안전성)
     *
     * @return Collection<int, string>
     */
    private function managerMasterOptions(): Collection
    {
        if (! Schema::hasTable('employee')) {
            return collect();
        }

        return Employee::query()
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
     * 저장된 담당자 표기를 직원 마스터 옵션 표기로 정렬합니다.
     *
     * 매칭이 없으면 원본을 그대로 유지해 과거/예외 데이터를 보존합니다.
     *
     * @param  Collection<int, string>  $masterOptions
     */
    private function alignManagerLabelToMasterOption(?string $raw, Collection $masterOptions): string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return '';
        }

        $rawKey = ManagerNameNormalizer::normalize($raw);
        if ($rawKey === '') {
            return $raw;
        }

        foreach ($masterOptions as $option) {
            if (ManagerNameNormalizer::normalize((string) $option) === $rawKey) {
                return (string) $option;
            }
        }

        return $raw;
    }
}
