<?php

namespace App\Livewire;

use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\SharedSupplyLabel;
use App\Models\SupportRecord;
use App\Models\TeamSchedule;
use App\Models\VehicleUsageLog;
use App\Support\SharedSupplyExcelImporter;
use App\Support\TeamMenuContext;
use App\Support\VehicleUsageLogRemark;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class SharedSupplyManager extends Component
{
    use WithFileUploads;

    private const RESET_CONFIRMATION_PHRASE = '초기화 실행';

    public string $month = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    public string $activeTab = 'user';

    public string $reservationView = 'all';

    public bool $showFormModal = false;

    public bool $showSupportReportPrompt = false;

    public string $supportReportPromptTeam = '';

    public bool $showResetModal = false;

    public ?int $editingSupplyId = null;

    public bool $viewOnly = false;

    public string $useDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public ?int $sharedSupplyItemId = null;

    public ?int $sharedSupplyLabelId = null;

    public string $scheduleCategoryCode = '';

    public string $title = '';

    public string $purpose = '';

    public ?int $vehicleOdometerBefore = null;

    public ?int $vehicleOdometerAfter = null;

    public string $vehicleUsagePurpose = '';

    public string $vehicleArrivalLocation = '';

    public string $vehicleLatestRemark = '';

    public string $vehicleLatestArrivalLocation = '';

    public string $vehicleUserName = '';

    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    /** @var array{inserted:int,updated:int,skipped:int}|null */
    public ?array $importSummary = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    public ?string $importNotice = null;

    public int $visibleSupplyLimit = 40;

    public int $visibleSupplyChunk = 40;

    public string $resetConfirmationText = '';

    public function mount(): void
    {
        $today = now();
        $this->month = $today->format('Y-m');
        $this->dateFrom = $today->format('Y-m-d');
        $this->dateTo = $today->copy()->endOfMonth()->format('Y-m-d');
    }

    public function previousMonth(): void
    {
        $this->syncDateRangeByMonth(
            Carbon::parse($this->dateFrom)->subMonth()->format('Y-m'),
        );
        $this->resetVisibleSupplies();
    }

    public function nextMonth(): void
    {
        $this->syncDateRangeByMonth(
            Carbon::parse($this->dateFrom)->addMonth()->format('Y-m'),
        );
        $this->resetVisibleSupplies();
    }

    public function goToday(): void
    {
        $this->syncDateRangeByMonth(now()->format('Y-m'));
        $this->resetVisibleSupplies();
    }

    public function updatingSearch(): void
    {
        $this->resetVisibleSupplies();
    }

    public function updatedMonth(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            $value = now()->format('Y-m');
        }

        $this->syncDateRangeByMonth($value);
        $this->resetVisibleSupplies();
    }

    public function updatedDateFrom(string $value): void
    {
        if (! $this->isValidDate($value)) {
            $this->dateFrom = Carbon::parse($this->month.'-01')->format('Y-m-d');
        }

        $this->normalizeDateRange();
        $this->resetVisibleSupplies();
    }

    public function updatedDateTo(string $value): void
    {
        if (! $this->isValidDate($value)) {
            $this->dateTo = Carbon::parse($this->month.'-01')->endOfMonth()->format('Y-m-d');
        }

        $this->normalizeDateRange();
        $this->resetVisibleSupplies();
    }

    public function applySearch(): void
    {
        $this->resetVisibleSupplies();
    }

    public function setActiveTab(string $tab): void
    {
        $allowedTabs = ['user', 'basic', 'daily', 'monthly', 'item'];
        if (! in_array($tab, $allowedTabs, true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetVisibleSupplies();
    }

    public function toggleReservationView(string $view): void
    {
        $allowedViews = ['reservation', 'personal'];
        if (! in_array($view, $allowedViews, true)) {
            return;
        }

        $this->reservationView = $this->reservationView === $view ? 'all' : $view;
        $this->resetVisibleSupplies();
    }

    public function loadMoreSupplies(): void
    {
        $this->visibleSupplyLimit += $this->visibleSupplyChunk;
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', SharedSupply::class);

        $this->resetForm();
        $this->useDate = now()->format('Y-m-d');
        $this->startTime = '09:00';
        $this->endTime = '10:00';
        $this->title = '[회의실] 신청 및 예약 (팀 회의)';
        $this->syncScheduleCategoryByTitle();
        $this->syncLabelByTitle();
        $this->showFormModal = true;
    }

    public function updatedTitle(string $value): void
    {
        if ($value === '') {
            $this->sharedSupplyItemId = null;

            return;
        }

        $this->syncScheduleCategoryByTitle();
        $this->syncLabelByTitle();
        $this->syncSharedSupplyItemByTitle();

        if ($this->sharedSupplyItemId === null) {
            return;
        }

        $selectedItem = SharedSupplyItem::query()->find($this->sharedSupplyItemId);
        if ($selectedItem === null) {
            $this->sharedSupplyItemId = null;

            return;
        }

        if ($this->isMeetingTitle()
            && ! $this->isMeetingItemName((string) $selectedItem->name)) {
            $this->sharedSupplyItemId = null;
        }

        if ($this->isLeaveTitle()
            && ! $this->isLeaveItemName((string) $selectedItem->name)) {
            $this->sharedSupplyItemId = null;
        }

        if ($this->shouldUseTitleAsItem()
            && (string) $selectedItem->name !== $this->title) {
            $this->sharedSupplyItemId = null;
        }

        if ($this->isVehicleTitle()
            && ! $this->isVehicleItemName((string) $selectedItem->name)) {
            $this->sharedSupplyItemId = null;
        }

        $this->syncVehicleOdometerBeforeByLatestLog();
    }

    public function updatedSharedSupplyItemId($value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->editingSupplyId === null) {
            $this->clearVehicleInputFieldsForCreate();
        }

        $this->syncVehicleOdometerBeforeByLatestLog();
    }

    public function openEditModal(int $id): void
    {
        $supply = SharedSupply::query()->with(['user', 'item', 'vehicleUsageLog'])->findOrFail($id);
        Gate::authorize('view', $supply);

        $vehicleLog = VehicleUsageLog::query()
            ->where('shared_supply_id', $supply->id)
            ->first();

        $this->editingSupplyId = $supply->id;
        $this->viewOnly = Gate::denies('update', $supply);
        $this->vehicleUserName = (string) ($supply->user?->name ?? '');
        $this->useDate = $supply->starts_at->format('Y-m-d');
        $this->startTime = $supply->starts_at->format('H:i');
        $this->endTime = $supply->ends_at->format('H:i');
        $this->sharedSupplyItemId = $supply->shared_supply_item_id;
        $this->sharedSupplyLabelId = $supply->shared_supply_label_id;
        $this->scheduleCategoryCode = (string) ($supply->schedule_category_code ?? '');
        $this->title = (string) $supply->title;
        $this->purpose = (string) ($supply->purpose ?? '');
        $this->vehicleOdometerBefore = $vehicleLog?->odometer_before;
        $this->vehicleOdometerAfter = $vehicleLog?->odometer_after;
        $this->vehicleUsagePurpose = (string) ($vehicleLog?->usage_purpose_name ?? '');
        $this->vehicleArrivalLocation = (string) ($vehicleLog?->arrival_location ?? '');
        $this->syncVehicleReferenceFieldsForItem(
            (int) $supply->shared_supply_item_id,
            $supply->id,
        );
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function closeSupportReportPrompt(): void
    {
        $this->showSupportReportPrompt = false;
        $this->supportReportPromptTeam = '';
    }

    public function navigateToSupportCreate(string $reportMode): void
    {
        if (! in_array($reportMode, ['institution', 'teacher'], true)) {
            return;
        }

        $teamMenu = $this->supportReportPromptTeam;
        if ($teamMenu === '') {
            return;
        }

        if ($teamMenu !== TeamMenuContext::MENU_COACH) {
            $reportMode = 'institution';
        }

        $this->closeSupportReportPrompt();

        $this->redirect(route('supports.create', [
            'team_menu' => $teamMenu,
            'report_mode' => $reportMode,
            'return' => 'shared-supplies',
        ]), navigate: true);
    }

    public function importFromExcel(): void
    {
        abort_unless(auth()->user()?->hasFullAccess(), 403, '관리자 권한이 필요합니다.');

        $this->importNotice = null;
        $validated = $this->validate([
            'importFile' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
        ], [
            'importFile.required' => '업로드할 엑셀 파일을 선택해 주세요.',
            'importFile.mimes' => '엑셀 파일(xls, xlsx)만 업로드할 수 있습니다.',
            'importFile.max' => '파일 크기는 20MB 이하여야 합니다.',
        ]);

        /** @var TemporaryUploadedFile $file */
        $file = $validated['importFile'];
        $result = app(SharedSupplyExcelImporter::class)->importFromFile(
            $file->getRealPath(),
            (int) auth()->id(),
        );

        $deleted = (int) ($result['deleted'] ?? 0);
        $this->importSummary = [
            'inserted' => $result['inserted'],
            'updated' => $result['updated'],
            'deleted' => $deleted,
            'skipped' => $result['skipped'],
        ];
        $this->importErrors = $result['errors'];
        $summaryMessage = "엑셀 반영 완료: 신규 {$result['inserted']}건, 업데이트 {$result['updated']}건, 삭제 {$deleted}건, 건너뜀 {$result['skipped']}건";
        $this->importNotice = $summaryMessage;
        $this->importFile = null;
        $this->resetVisibleSupplies();
    }

    public function openResetModal(): void
    {
        abort_unless(auth()->user()?->hasFullAccess(), 403, '관리자 권한이 필요합니다.');

        $this->resetValidation('resetConfirmationText');
        $this->resetConfirmationText = '';
        $this->showResetModal = true;
    }

    public function closeResetModal(): void
    {
        $this->showResetModal = false;
        $this->resetValidation('resetConfirmationText');
        $this->resetConfirmationText = '';
    }

    public function resetSharedSupplyData(): void
    {
        abort_unless(auth()->user()?->hasFullAccess(), 403, '관리자 권한이 필요합니다.');

        $this->validate([
            'resetConfirmationText' => ['required', Rule::in([self::RESET_CONFIRMATION_PHRASE])],
        ], [
            'resetConfirmationText.required' => '초기화 확인 문구를 입력해 주세요.',
            'resetConfirmationText.in' => '확인 문구가 일치하지 않습니다. "초기화 실행"을 정확히 입력해 주세요.',
        ]);

        $deletedCounts = DB::transaction(function (): array {
            $hasTeamScheduleSourceColumns = Schema::hasTable('team_schedules')
                && Schema::hasColumn('team_schedules', 'source_type')
                && Schema::hasColumn('team_schedules', 'source_id');
            $hasSharedSupplyUserMappingsTable = Schema::hasTable('shared_supply_user_mappings');

            $sharedSupplyCount = SharedSupply::query()->count();
            $vehicleLogCount = VehicleUsageLog::query()->count();
            $teamScheduleCount = $hasTeamScheduleSourceColumns
                ? TeamSchedule::query()->where('source_type', 'shared_supply')->count()
                : 0;
            $mappingCount = $hasSharedSupplyUserMappingsTable
                ? DB::table('shared_supply_user_mappings')->count()
                : 0;
            $itemCount = SharedSupplyItem::query()->count();
            $labelCount = SharedSupplyLabel::query()->count();

            if ($hasTeamScheduleSourceColumns) {
                TeamSchedule::query()
                    ->where('source_type', 'shared_supply')
                    ->delete();
            }
            VehicleUsageLog::query()->delete();
            SharedSupply::query()->delete();
            if ($hasSharedSupplyUserMappingsTable) {
                DB::table('shared_supply_user_mappings')->delete();
            }
            SharedSupplyItem::query()->delete();
            SharedSupplyLabel::query()->delete();
            $this->seedDefaultSharedSupplyItems();
            $this->seedDefaultSharedSupplyLabels();

            return [
                'shared_supplies' => $sharedSupplyCount,
                'vehicle_usage_logs' => $vehicleLogCount,
                'team_schedules' => $teamScheduleCount,
                'shared_supply_user_mappings' => $mappingCount,
                'shared_supply_items' => $itemCount,
                'shared_supply_labels' => $labelCount,
            ];
        });

        $this->resetConfirmationText = '';
        $this->showResetModal = false;
        $this->resetVisibleSupplies();
        $this->importSummary = null;
        $this->importErrors = [];
        $this->importNotice = '공용품 관리 데이터 초기화가 완료되었습니다.';

        session()->flash(
            'success',
            "초기화 완료 (이력 {$deletedCounts['shared_supplies']}건, 차량기록 {$deletedCounts['vehicle_usage_logs']}건, ".
            "팀일정 {$deletedCounts['team_schedules']}건, 매핑 {$deletedCounts['shared_supply_user_mappings']}건, ".
            "공용품 {$deletedCounts['shared_supply_items']}건, 라벨 {$deletedCounts['shared_supply_labels']}건)"
        );
    }

    public function save(): void
    {
        $this->syncSharedSupplyItemByTitle();
        $validated = $this->validate();
        $selectedItem = SharedSupplyItem::query()->find($validated['sharedSupplyItemId']);
        $selectedLabel = SharedSupplyLabel::query()->find($validated['sharedSupplyLabelId']);
        $startsAt = Carbon::parse($validated['useDate'].' '.$validated['startTime']);
        $endsAt = Carbon::parse($validated['useDate'].' '.$validated['endTime']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $this->addError('endTime', '종료 시간은 시작 시간보다 늦어야 합니다.');

            return;
        }

        if ($this->editingSupplyId === null && $this->isVehicleTitle() && $this->selectedVehicleHasOpenTrip()) {
            $this->dispatch('shared-supply-show-alert', message: '해당 차량은 아직 사용 중입니다. 이전 예약을 취소하거나 사용 완료를 기록해 주세요.');
            $this->addError('sharedSupplyItemId', '해당 차량은 아직 사용 중입니다. 이전 예약을 취소하거나 사용 완료를 기록해 주세요.');

            return;
        }

        if ($this->isVehicleTitle() && $this->vehicleOdometerAfter !== null && $this->vehicleOdometerBefore !== null
            && $this->vehicleOdometerAfter < $this->vehicleOdometerBefore) {
            $this->addError('vehicleOdometerAfter', '주행 후 계기판 거리는 주행 전보다 크거나 같아야 합니다.');

            return;
        }

        if ($this->requiresReservationConflictCheck($validated['title'])) {
            $conflictExists = SharedSupply::query()
                ->where('shared_supply_item_id', $validated['sharedSupplyItemId'])
                ->whereDate('starts_at', $validated['useDate'])
                ->when($this->editingSupplyId !== null, fn (Builder $query): Builder => $query->whereKeyNot($this->editingSupplyId))
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->where(fn (Builder $query): Builder => $query->where('title', 'like', '%신청 및 예약%'))
                ->exists();

            if ($conflictExists) {
                $this->addError('startTime', '같은 공용품의 예약 시간이 겹칩니다. 시간을 조정해 주세요.');

                return;
            }
        }

        $user = auth()->user();

        if ($this->editingSupplyId !== null) {
            $supply = SharedSupply::query()->findOrFail($this->editingSupplyId);
            Gate::authorize('update', $supply);

            $payload = [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'shared_supply_item_id' => $validated['sharedSupplyItemId'],
                'shared_supply_label_id' => $validated['sharedSupplyLabelId'],
                'schedule_category_code' => $validated['scheduleCategoryCode'] ?: null,
                'title' => $validated['title'],
                'purpose' => $validated['purpose'] ?: null,
                'updated_by' => $user?->id,
            ];
            $payload = $this->appendLegacyCompatiblePayload($payload, $selectedItem?->name, $selectedLabel?->name);

            $supply->update($payload);
            $this->syncVehicleUsageLog($supply, $user?->id);
        } else {
            Gate::authorize('create', SharedSupply::class);

            $payload = [
                'user_id' => $user?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'shared_supply_item_id' => $validated['sharedSupplyItemId'],
                'shared_supply_label_id' => $validated['sharedSupplyLabelId'],
                'schedule_category_code' => $validated['scheduleCategoryCode'] ?: null,
                'title' => $validated['title'],
                'purpose' => $validated['purpose'] ?: null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ];
            $payload = $this->appendLegacyCompatiblePayload($payload, $selectedItem?->name, $selectedLabel?->name);

            $supply = SharedSupply::query()->create($payload);
            $this->syncVehicleUsageLog($supply, $user?->id);
        }

        $promptTeamMenu = $this->promptSupportReportTeamMenu();
        if ($this->shouldPromptSupportReport($promptTeamMenu)) {
            $this->closeFormModal();
            $this->supportReportPromptTeam = $promptTeamMenu;
            $this->showSupportReportPrompt = true;
            session()->flash('success', '운행 기록이 저장되었습니다.');

            return;
        }

        session()->flash('success', '공용품 사용 내역이 저장되었습니다.');
        $this->closeFormModal();
    }

    public function delete(): void
    {
        if ($this->editingSupplyId === null) {
            return;
        }

        $supply = SharedSupply::query()->findOrFail($this->editingSupplyId);
        Gate::authorize('delete', $supply);
        $supply->delete();

        session()->flash('success', '공용품 사용 내역이 삭제되었습니다.');
        $this->closeFormModal();
    }

    protected function rules(): array
    {
        return [
            'useDate' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'sharedSupplyItemId' => ['required', 'integer', Rule::exists('shared_supply_items', 'id')],
            'sharedSupplyLabelId' => ['required', 'integer', Rule::exists('shared_supply_labels', 'id')],
            'scheduleCategoryCode' => ['nullable', Rule::in(array_keys($this->scheduleCategoryOptions()))],
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'vehicleUsagePurpose' => [
                Rule::requiredIf(fn (): bool => $this->isVehicleTitle() && $this->editingSupplyId === null),
                'nullable',
                'string',
                Rule::in($this->allowedVehicleUsagePurposeNamesForValidation()),
            ],
            'vehicleOdometerBefore' => [Rule::requiredIf(fn (): bool => $this->isVehicleTitle() && $this->editingSupplyId === null), 'nullable', 'integer', 'min:0'],
            'vehicleOdometerAfter' => ['nullable', 'integer', 'min:0'],
            'vehicleArrivalLocation' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function render(): View
    {
        $supplyLabels = SharedSupplyLabel::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $allSupplyItems = SharedSupplyItem::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $supplyItems = $this->filterSupplyItemsByTitle($allSupplyItems);

        $baseQuery = SharedSupply::query()
            ->with(['user', 'item', 'label', 'vehicleUsageLog'])
            ->whereDate('starts_at', '>=', $this->dateFrom)
            ->whereDate('starts_at', '<=', $this->dateTo)
            ->when($this->reservationView === 'reservation', function (Builder $query): void {
                $query->where('title', 'like', '%신청 및 예약%');
            })
            ->when($this->reservationView === 'personal', function (Builder $query): void {
                $query->where('title', 'not like', '%신청 및 예약%');
            })
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $searchQuery): void {
                    $searchQuery->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('purpose', 'like', '%'.$this->search.'%')
                        ->orWhereHas('item', function (Builder $itemQuery): void {
                            $itemQuery->where('code', 'like', '%'.$this->search.'%')
                                ->orWhere('name', 'like', '%'.$this->search.'%');
                        })
                        ->orWhereHas('label', function (Builder $labelQuery): void {
                            $labelQuery->where('code', 'like', '%'.$this->search.'%')
                                ->orWhere('name', 'like', '%'.$this->search.'%');
                        })
                        ->orWhereHas('user', function (Builder $userQuery): void {
                            $userQuery->where('name', 'like', '%'.$this->search.'%');
                        })
                        ->when(
                            Schema::hasTable('vehicle_usage_logs'),
                            function (Builder $searchQuery): void {
                                $searchQuery->orWhereHas('vehicleUsageLog', function (Builder $vehicleLogQuery): void {
                                    $vehicleLogQuery->where('usage_purpose_name', 'like', '%'.$this->search.'%');
                                    if (Schema::hasColumn('vehicle_usage_logs', 'arrival_location')) {
                                        $vehicleLogQuery->orWhere('arrival_location', 'like', '%'.$this->search.'%');
                                    }
                                });
                            },
                        );
                });
            });

        $suppliesQuery = (clone $baseQuery)
            ->orderBy('starts_at')
            ->orderBy('id');

        $totalSupplies = (clone $suppliesQuery)->count();

        $supplies = (clone $suppliesQuery)
            ->limit($this->visibleSupplyLimit)
            ->get();

        $hasMoreSupplies = $totalSupplies > $supplies->count();

        $allSupplies = (clone $baseQuery)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $dailyGroups = $allSupplies
            ->groupBy(static fn (SharedSupply $supply): string => $supply->starts_at->toDateString());

        $itemGroups = $allSupplies
            ->groupBy(static fn (SharedSupply $supply): string => (string) ($supply->item?->name ?? '-'));

        $monthlySummary = [
            'total_count' => $allSupplies->count(),
            'user_count' => $allSupplies->pluck('user_id')->filter()->unique()->count(),
            'item_count' => $allSupplies->pluck('shared_supply_item_id')->filter()->unique()->count(),
            'reservation_count' => $allSupplies->filter(static fn (SharedSupply $supply): bool => str_contains((string) $supply->title, '신청 및 예약'))->count(),
        ];

        $monthlyByCategory = $allSupplies
            ->groupBy(static fn (SharedSupply $supply): string => (string) ($supply->schedule_category_code ?? ''))
            ->map(function ($group, string $code): array {
                $label = $code !== '' ? ($this->scheduleCategoryOptions()[$code] ?? '기타') : '미분류';

                return [
                    'code' => $code,
                    'label' => $label,
                    'count' => $group->count(),
                ];
            })
            ->values()
            ->sortByDesc('count')
            ->values();

        return view('livewire.shared-supply-manager', [
            'supplies' => $supplies,
            'hasMoreSupplies' => $hasMoreSupplies,
            'dailyGroups' => $dailyGroups,
            'itemGroups' => $itemGroups,
            'monthlySummary' => $monthlySummary,
            'monthlyByCategory' => $monthlyByCategory,
            'supplyLabels' => $supplyLabels,
            'supplyItems' => $supplyItems,
            'scheduleCategoryOptions' => $this->scheduleCategoryOptions(),
            'titleOptions' => $this->titleOptions(),
            'vehicleUsagePurposeSelectOptions' => $this->vehicleUsagePurposeSelectOptions(),
            'monthLabel' => Carbon::parse($this->month.'-01')->format('Y년 n월'),
        ]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingSupplyId = null;
        $this->viewOnly = false;
        $this->useDate = '';
        $this->startTime = '';
        $this->endTime = '';
        $this->sharedSupplyItemId = null;
        $this->sharedSupplyLabelId = null;
        $this->scheduleCategoryCode = '';
        $this->title = '';
        $this->purpose = '';
        $this->vehicleOdometerBefore = null;
        $this->vehicleOdometerAfter = null;
        $this->vehicleUsagePurpose = '';
        $this->vehicleArrivalLocation = '';
        $this->vehicleLatestRemark = '';
        $this->vehicleLatestArrivalLocation = '';
        $this->vehicleUserName = (string) (auth()->user()?->name ?? '');
    }

    /**
     * DB가 레거시 스키마(item_name, label)를 아직 유지하는 경우를 함께 지원한다.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function appendLegacyCompatiblePayload(array $payload, ?string $itemName, ?string $labelName): array
    {
        if (Schema::hasColumn('shared_supplies', 'item_name')) {
            $payload['item_name'] = $itemName ?? '';
        }

        if (Schema::hasColumn('shared_supplies', 'label')) {
            $payload['label'] = $labelName ?? '사용자별';
        }

        return $payload;
    }

    private function syncLabelByTitle(): void
    {
        $labelCode = $this->isMeetingTitle() ? '02' : '01';
        $labelId = SharedSupplyLabel::query()->where('code', $labelCode)->value('id');
        if ($labelId !== null) {
            $this->sharedSupplyLabelId = (int) $labelId;
        }
    }

    private function filterSupplyItemsByTitle(Collection $items): Collection
    {
        if ($this->isLeaveTitle()) {
            $this->ensureLeaveItemsExist();

            return $items->filter(fn (SharedSupplyItem $item): bool => $this->isLeaveItemName((string) $item->name))->values();
        }

        if ($this->isMeetingTitle()) {
            return $items->filter(fn (SharedSupplyItem $item): bool => $this->isMeetingItemName((string) $item->name))->values();
        }

        if ($this->isVehicleTitle()) {
            return $items->filter(fn (SharedSupplyItem $item): bool => $this->isVehicleItemName((string) $item->name))->values();
        }

        if ($this->shouldUseTitleAsItem()) {
            $item = $this->ensureItemByName($this->title);

            return SharedSupplyItem::query()->whereKey($item->id)->get();
        }

        return $items;
    }

    private function syncSharedSupplyItemByTitle(): void
    {
        if ($this->title === '') {
            return;
        }

        if ($this->isLeaveTitle()) {
            $this->ensureLeaveItemsExist();

            return;
        }

        if ($this->shouldUseTitleAsItem()) {
            $this->sharedSupplyItemId = $this->ensureItemByName($this->title)->id;
        }
    }

    private function ensureItemByName(string $name): SharedSupplyItem
    {
        $existing = SharedSupplyItem::query()->where('name', $name)->first();
        if ($existing !== null) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return $existing;
        }

        return SharedSupplyItem::query()->create([
            'code' => $this->generateNextItemCode(),
            'name' => $name,
            'is_active' => true,
            'sort_order' => ((int) SharedSupplyItem::query()->max('sort_order')) + 1,
        ]);
    }

    private function ensureLeaveItemsExist(): void
    {
        foreach (['오전 반차', '오후 반차', '시차'] as $leaveItemName) {
            $this->ensureItemByName($leaveItemName);
        }
    }

    private function generateNextItemCode(): string
    {
        $maxNumericCode = SharedSupplyItem::query()
            ->pluck('code')
            ->map(fn (string $code): int => ctype_digit($code) ? (int) $code : 0)
            ->max() ?? 0;

        return str_pad((string) ($maxNumericCode + 1), 5, '0', STR_PAD_LEFT);
    }

    private function isMeetingTitle(): bool
    {
        return str_contains($this->title, '회의실') || str_contains($this->title, '[팀회의]');
    }

    private function isVehicleTitle(): bool
    {
        return str_contains($this->title, '차량배차');
    }

    private function isVehicleTitleFor(string $title): bool
    {
        return str_contains($title, '차량배차');
    }

    private function isLeaveTitle(): bool
    {
        return str_starts_with($this->title, '[휴가]');
    }

    private function shouldUseTitleAsItem(): bool
    {
        return $this->shouldUseTitleAsItemFor($this->title);
    }

    private function isMeetingItemName(string $itemName): bool
    {
        $normalized = preg_replace('/[\s\p{P}\p{S}]+/u', '', mb_strtolower(trim($itemName))) ?? '';

        return str_contains($normalized, 'room') || str_contains($normalized, 'grapeseedservices');
    }

    private function isLeaveItemName(string $itemName): bool
    {
        $normalized = preg_replace('/[\s\p{P}\p{S}]+/u', '', mb_strtolower(trim($itemName))) ?? '';
        $leaveItemNames = ['오전반차', '오후반차', '시차'];

        return in_array($normalized, $leaveItemNames, true);
    }

    private function isVehicleItemName(string $itemName): bool
    {
        if ($this->isMeetingItemName($itemName) || $this->isLeaveItemName($itemName) || $this->isTitleBasedItemName($itemName)) {
            return false;
        }

        return preg_match('/\d{2,3}[가-힣]\d{4}/u', $itemName) === 1;
    }

    private function isTitleBasedItemName(string $itemName): bool
    {
        return array_key_exists($itemName, $this->titleOptions()) && $this->shouldUseTitleAsItemFor($itemName);
    }

    private function shouldUseTitleAsItemFor(string $title): bool
    {
        if ($title === '' || str_contains($title, '차량배차') || str_contains($title, '회의실') || str_starts_with($title, '[휴가]')) {
            return false;
        }

        return array_key_exists($title, $this->titleOptions());
    }

    private function requiresReservationConflictCheck(string $title): bool
    {
        return str_contains($title, '신청 및 예약');
    }

    private function shouldPromptSupportReport(string $teamMenu): bool
    {
        if ($this->editingSupplyId === null) {
            return false;
        }

        if (! $this->isVehicleTitle()) {
            return false;
        }

        if ($this->vehicleOdometerAfter === null) {
            return false;
        }

        if (in_array($this->vehicleUsagePurpose, ['출퇴근', '업무외'], true)) {
            return false;
        }

        if (! in_array($teamMenu, [TeamMenuContext::MENU_CO, TeamMenuContext::MENU_COACH], true)) {
            return false;
        }

        return ! $this->hasSupportRecordForDate($this->useDate);
    }

    private function promptSupportReportTeamMenu(): string
    {
        $teamMenu = TeamMenuContext::activeMenu(auth()->user());

        return in_array($teamMenu, [TeamMenuContext::MENU_CO, TeamMenuContext::MENU_COACH], true)
            ? $teamMenu
            : '';
    }

    private function hasSupportRecordForDate(string $supportDate): bool
    {
        $user = auth()->user();
        if ($user === null || trim($supportDate) === '') {
            return false;
        }

        if (! SupportRecord::tableHasColumn('Support_Date') || ! SupportRecord::tableHasColumn('TR_Name')) {
            return false;
        }

        return SupportRecord::query()
            ->where('TR_Name', $user->nameForCoReports())
            ->whereDate('Support_Date', $supportDate)
            ->exists();
    }

    private function syncVehicleUsageLog(SharedSupply $supply, ?int $actorId): void
    {
        if (! $this->isVehicleTitle()) {
            $supply->vehicleUsageLog()?->delete();

            return;
        }

        $hasVehicleInput = $this->vehicleUsagePurpose !== ''
            || $this->vehicleOdometerBefore !== null
            || $this->vehicleOdometerAfter !== null
            || $this->vehicleArrivalLocation !== '';

        if (! $hasVehicleInput) {
            return;
        }

        $existingLog = $supply->vehicleUsageLog()->first();
        $logPayload = $this->buildVehicleUsageLogPayload($supply, $actorId, $existingLog);
        if ($existingLog === null) {
            $logPayload['shared_supply_id'] = $supply->id;
            $logPayload['created_by'] = $actorId;
            VehicleUsageLog::query()->create($logPayload);

            return;
        }

        $existingLog->update($logPayload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVehicleUsageLogPayload(SharedSupply $supply, ?int $actorId, ?VehicleUsageLog $existingLog = null): array
    {
        $distance = null;
        if ($this->vehicleOdometerBefore !== null && $this->vehicleOdometerAfter !== null) {
            $distance = max(0, $this->vehicleOdometerAfter - $this->vehicleOdometerBefore);
        }

        $logPayload = [
            'user_id' => (int) ($supply->user_id ?? auth()->id()),
            'vehicle_name' => (string) ($supply->item?->name ?? ''),
            'usage_purpose_name' => $this->vehicleUsagePurpose !== '' ? $this->vehicleUsagePurpose : null,
            'remarks' => $this->purpose !== '' ? $this->purpose : null,
            'driven_on' => $supply->starts_at->toDateString(),
            'updated_by' => $actorId,
        ];

        if ($this->vehicleOdometerBefore !== null) {
            $logPayload['odometer_before'] = $this->vehicleOdometerBefore;
        }

        if ($this->vehicleOdometerAfter !== null) {
            $logPayload['odometer_after'] = $this->vehicleOdometerAfter;
        }

        if ($distance !== null) {
            $logPayload['distance'] = $distance;
        }

        if (Schema::hasColumn('vehicle_usage_logs', 'arrival_location')) {
            $logPayload['arrival_location'] = $this->vehicleArrivalLocation !== '' ? $this->vehicleArrivalLocation : null;
        }

        return $logPayload;
    }

    private function clearVehicleInputFieldsForCreate(): void
    {
        $this->vehicleOdometerBefore = null;
        $this->vehicleOdometerAfter = null;
        $this->vehicleUsagePurpose = '';
        $this->vehicleArrivalLocation = '';
        $this->vehicleLatestRemark = '';
        $this->vehicleLatestArrivalLocation = '';
    }

    private function syncVehicleReferenceFieldsForItem(int $itemId, ?int $excludeSharedSupplyId = null): void
    {
        $latestVehicleLog = $this->latestVehicleUsageLogForItemId($itemId, $excludeSharedSupplyId);
        if ($latestVehicleLog === null) {
            $this->vehicleLatestRemark = '';
            $this->vehicleLatestArrivalLocation = '';

            return;
        }

        $this->vehicleLatestArrivalLocation = (string) ($latestVehicleLog->arrival_location ?? '');
        $this->vehicleLatestRemark = VehicleUsageLogRemark::combineArrivalAndReason(
            $this->vehicleLatestArrivalLocation,
            VehicleUsageLogRemark::forDisplay($latestVehicleLog->remarks),
        );
    }

    private function syncVehicleOdometerBeforeByLatestLog(): void
    {
        if (! $this->isVehicleTitle() || $this->editingSupplyId !== null) {
            if (! $this->isVehicleTitle()) {
                $this->vehicleLatestRemark = '';
                $this->vehicleLatestArrivalLocation = '';
            }

            return;
        }

        if ($this->sharedSupplyItemId === null) {
            $this->vehicleLatestRemark = '';
            $this->vehicleLatestArrivalLocation = '';

            return;
        }

        $this->syncVehicleReferenceFieldsForItem((int) $this->sharedSupplyItemId);

        $latestVehicleLog = $this->latestVehicleUsageLogForCurrentItem();
        if ($latestVehicleLog === null) {
            return;
        }

        if ($this->vehicleOdometerBefore !== null) {
            return;
        }

        if ($latestVehicleLog->odometer_after !== null) {
            $this->vehicleOdometerBefore = (int) $latestVehicleLog->odometer_after;

            return;
        }

        if ($latestVehicleLog->odometer_before !== null) {
            $this->vehicleOdometerBefore = (int) $latestVehicleLog->odometer_before;
        }
    }

    private function selectedVehicleHasOpenTrip(): bool
    {
        if ($this->sharedSupplyItemId === null) {
            return false;
        }

        $latestVehicleLog = $this->latestVehicleUsageLogForCurrentItem();
        if ($latestVehicleLog === null) {
            return false;
        }

        return $latestVehicleLog->odometer_after === null;
    }

    private function latestVehicleUsageLogForCurrentItem(): ?VehicleUsageLog
    {
        if ($this->sharedSupplyItemId === null) {
            return null;
        }

        return $this->latestVehicleUsageLogForItemId((int) $this->sharedSupplyItemId);
    }

    private function latestVehicleUsageLogBaseQuery(?int $excludeSharedSupplyId = null): Builder
    {
        $query = VehicleUsageLog::query()
            ->where(function (Builder $builder): void {
                $builder->whereNotNull('odometer_after')
                    ->orWhereNotNull('odometer_before');
            })
            ->orderByDesc('driven_on')
            ->orderByDesc('id');

        if ($excludeSharedSupplyId !== null) {
            $query->where(function (Builder $builder) use ($excludeSharedSupplyId): void {
                $builder->where('shared_supply_id', '!=', $excludeSharedSupplyId)
                    ->orWhereNull('shared_supply_id');
            });
        }

        return $query;
    }

    private function latestVehicleUsageLogForItemId(int $itemId, ?int $excludeSharedSupplyId = null): ?VehicleUsageLog
    {
        $itemName = (string) (SharedSupplyItem::query()->whereKey($itemId)->value('name') ?? '');
        if ($itemName === '') {
            return null;
        }

        $baseQuery = $this->latestVehicleUsageLogBaseQuery($excludeSharedSupplyId);

        $exactMatched = (clone $baseQuery)
            ->where('vehicle_name', $itemName)
            ->first();
        if ($exactMatched instanceof VehicleUsageLog) {
            return $exactMatched;
        }

        $plateNumber = $this->extractVehiclePlateNumber($itemName);
        if ($plateNumber !== null) {
            $matchedByPlate = (clone $baseQuery)
                ->where('vehicle_name', 'like', '%'.$plateNumber.'%')
                ->first();
            if ($matchedByPlate instanceof VehicleUsageLog) {
                return $matchedByPlate;
            }
        }

        return null;
    }

    private function extractVehiclePlateNumber(string $text): ?string
    {
        if (preg_match('/\d{2,3}[가-힣]\d{4}/u', $text, $matches) !== 1) {
            return null;
        }

        return trim((string) ($matches[0] ?? '')) ?: null;
    }

    private function resetVisibleSupplies(): void
    {
        $this->visibleSupplyLimit = $this->visibleSupplyChunk;
    }

    private function syncDateRangeByMonth(string $month): void
    {
        $monthStart = Carbon::parse($month.'-01');

        $this->month = $monthStart->format('Y-m');
        $this->dateFrom = $monthStart->copy()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $monthStart->copy()->endOfMonth()->format('Y-m-d');
    }

    private function normalizeDateRange(): void
    {
        if (! $this->isValidDate($this->dateFrom) || ! $this->isValidDate($this->dateTo)) {
            $this->syncDateRangeByMonth($this->month);

            return;
        }

        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        if ($from->gt($to)) {
            $this->dateTo = $from->format('Y-m-d');
        }

        $this->month = Carbon::parse($this->dateFrom)->format('Y-m');
    }

    private function isCurrentMonthRange(): bool
    {
        $currentMonthStart = now()->startOfMonth()->format('Y-m-d');
        $currentMonthEnd = now()->endOfMonth()->format('Y-m-d');

        return $this->dateFrom === $currentMonthStart
            && $this->dateTo === $currentMonthEnd;
    }

    private function isValidDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function syncScheduleCategoryByTitle(): void
    {
        if (preg_match('/^\[([^\]]+)\]/u', $this->title, $matches) === 1) {
            $categoryName = trim((string) ($matches[1] ?? ''));
            $categoryCode = array_search($categoryName, $this->scheduleCategoryOptions(), true);
            if ($categoryCode !== false) {
                $this->scheduleCategoryCode = (string) $categoryCode;

                return;
            }

            if ($categoryName === '출장 차량배차') {
                $this->scheduleCategoryCode = '002';

                return;
            }

            if ($categoryName === '회의실' && str_contains($this->title, '(팀 회의)')) {
                $this->scheduleCategoryCode = '006';

                return;
            }
        }

        if ($this->scheduleCategoryCode === '') {
            $this->scheduleCategoryCode = '006';
        }
    }

    /**
     * @return array<string, string>
     */
    private function scheduleCategoryOptions(): array
    {
        return [
            '001' => '휴가',
            '002' => '출장',
            '003' => '해외출장',
            '004' => '전체회의',
            '005' => '본부회의',
            '006' => '팀회의',
            '007' => '사내외행사',
            '009' => '경조사',
            '011' => '건강검진',
            '012' => '사내외업무',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedVehicleUsagePurposeNames(): array
    {
        return [
            '일반업무',
            '출퇴근',
            '업무외',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedVehicleUsagePurposeNamesForValidation(): array
    {
        $allowed = $this->allowedVehicleUsagePurposeNames();
        $current = trim($this->vehicleUsagePurpose);

        if ($this->editingSupplyId !== null && $current !== '' && ! in_array($current, $allowed, true)) {
            $allowed[] = $current;
        }

        return $allowed;
    }

    /**
     * @return array<string, string>
     */
    private function vehicleUsagePurposeSelectOptions(): array
    {
        $options = [
            '일반업무' => '00001 - 일반업무',
            '출퇴근' => '00002 - 출퇴근',
            '업무외' => '00003 - 업무외',
        ];

        $current = trim($this->vehicleUsagePurpose);
        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function titleOptions(): array
    {
        return [
            '[출장 차량배차] 신청 및 예약' => '[출장 차량배차] 신청 및 예약',
            '[출장] 출장' => '[출장] 출장',
            '[회의실] 신청 및 예약 (팀 회의)' => '[회의실] 신청 및 예약 (팀 회의)',
            '[회의실] 신청 및 예약(기타)' => '[회의실] 신청 및 예약(기타)',
            '[해외출장] 해외출장' => '[해외출장] 해외출장',
            '[휴가] 연차휴가' => '[휴가] 연차휴가',
            '[사내외업무] 사내외업무' => '[사내외업무] 사내외업무',
            '[사내외행사] 사내외행사' => '[사내외행사] 사내외행사',
            '[경조사] 경조사' => '[경조사] 경조사',
            '[건강검진] 건강검진' => '[건강검진] 건강검진',
        ];
    }

    private function seedDefaultSharedSupplyItems(): void
    {
        DB::table('shared_supply_items')->upsert($this->defaultSharedSupplyItems(), ['code'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }

    private function seedDefaultSharedSupplyLabels(): void
    {
        DB::table('shared_supply_labels')->upsert($this->defaultSharedSupplyLabels(), ['code'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }

    /**
     * @return array<int, array{code:string,name:string,is_active:bool,sort_order:int,created_at:\Illuminate\Support\Carbon,updated_at:\Illuminate\Support\Carbon}>
     */
    private function defaultSharedSupplyItems(): array
    {
        return [
            ['code' => '00003', 'name' => '04부8326 (투싼/경유)-구미김천역', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00005', 'name' => '29구9162 (투싼/경유)', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00008', 'name' => '62노5836 (아반테/경유)', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00010', 'name' => '169허7622(뉴그랜저)', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00011', 'name' => '236주3346 (투싼/가솔린)', 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00012', 'name' => '100누7588 (투싼/가솔린)-부산역', 'is_active' => true, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00013', 'name' => 'Grape Room', 'is_active' => true, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00014', 'name' => 'Jenny Room', 'is_active' => true, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00015', 'name' => 'Jonny Room', 'is_active' => true, 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00016', 'name' => '133누7691(투싼/가솔린)', 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00017', 'name' => 'GrapeSEED Services', 'is_active' => true, 'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00018', 'name' => '오전 반차', 'is_active' => true, 'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00019', 'name' => '오후 반차', 'is_active' => true, 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00020', 'name' => '시차', 'is_active' => true, 'sort_order' => 14, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00021', 'name' => '[출장] 출장', 'is_active' => true, 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00022', 'name' => '[본부회의] 본부회의', 'is_active' => true, 'sort_order' => 16, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00023', 'name' => '[전체회의] 전체회의', 'is_active' => true, 'sort_order' => 17, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00024', 'name' => '[해외출장] 해외출장', 'is_active' => true, 'sort_order' => 18, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00025', 'name' => '[사내외업무] 사내외업무', 'is_active' => true, 'sort_order' => 19, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00026', 'name' => '[사내외행사] 사내외행사', 'is_active' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00027', 'name' => '[경조사] 경조사', 'is_active' => true, 'sort_order' => 21, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00028', 'name' => '[건강검진] 건강검진', 'is_active' => true, 'sort_order' => 22, 'created_at' => now(), 'updated_at' => now()],
        ];
    }

    /**
     * @return array<int, array{code:string,name:string,is_active:bool,sort_order:int,created_at:\Illuminate\Support\Carbon,updated_at:\Illuminate\Support\Carbon}>
     */
    private function defaultSharedSupplyLabels(): array
    {
        return [
            ['code' => '01', 'name' => '차량배차', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '02', 'name' => '회의실', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ];
    }
}
