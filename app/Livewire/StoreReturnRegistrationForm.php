<?php

namespace App\Livewire;

use App\Models\Institution;
use App\Models\StoreReturnRegistration;
use App\Support\StoreReturnEcountProductOptions;
use App\Support\StoreReturnTeamsNotifier;
use App\Support\TeamMenuContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class StoreReturnRegistrationForm extends Component
{
    use WithPagination;

    public string $returnDate = '';

    public string $institutionKeyword = '';

    public string $institutionSkCode = '';

    public string $institutionName = '';

    public string $freight = '';

    public string $csTeam = '';

    /**
     * @var array<int, array{itemName: string, quantity: string, status: string, notes: string}>
     */
    public array $itemRows = [];

    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 20;

    public bool $showCreateModal = false;

    public bool $showDetailModal = false;

    public bool $detailEditMode = false;

    public ?int $detailAnchorId = null;

    public string $detailReturnDate = '';

    public string $detailInstitutionKeyword = '';

    public string $detailInstitutionSkCode = '';

    public string $detailFreight = '';

    public string $detailCsTeam = '';

    /**
     * @var array<int, int>
     */
    public array $detailOriginalRegistrationIds = [];

    /**
     * @var array<int, array{id: int|null, itemName: string, quantity: string, status: string, notes: string}>
     */
    public array $detailItemRows = [];

    public ?string $teamMenu = null;

    private ?string $lockedInstitutionKeyword = null;

    private ?string $lockedDetailInstitutionKeyword = null;

    public function mount(): void
    {
        $this->returnDate = Carbon::now()->format('Y-m-d');
        $this->freight = '';
        $this->itemRows = [$this->defaultItemRow()];
        $this->teamMenu = $this->resolveTeamMenu();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, Institution>
     */
    public function getInstitutionSuggestionsProperty(): Collection
    {
        if (filled($this->institutionSkCode)) {
            return collect();
        }

        $keyword = trim($this->institutionKeyword);
        if ($keyword === '' || ! Schema::hasTable('S_AccountName')) {
            return collect();
        }

        $normalizedKeyword = preg_replace('/\s+/u', '', $keyword) ?? '';
        if ($normalizedKeyword === '') {
            return collect();
        }

        return Institution::query()
            ->with('accountInfo')
            ->whereNotNull('SKcode')
            ->whereDoesntHave('accountInfo', function (Builder $query): void {
                $query->where('Customer_Type', 'like', '%해지%');
            })
            ->where(function (Builder $query) use ($normalizedKeyword): void {
                $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                    ->orWhereRaw("REPLACE(SKcode, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
            })
            ->orderBy('AccountName')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, Institution>
     */
    public function getDetailInstitutionSuggestionsProperty(): Collection
    {
        if (filled($this->detailInstitutionSkCode)) {
            return collect();
        }

        $keyword = trim($this->detailInstitutionKeyword);
        if ($keyword === '' || ! Schema::hasTable('S_AccountName')) {
            return collect();
        }

        $normalizedKeyword = preg_replace('/\s+/u', '', $keyword) ?? '';
        if ($normalizedKeyword === '') {
            return collect();
        }

        return Institution::query()
            ->with('accountInfo')
            ->whereNotNull('SKcode')
            ->whereDoesntHave('accountInfo', function (Builder $query): void {
                $query->where('Customer_Type', 'like', '%해지%');
            })
            ->where(function (Builder $query) use ($normalizedKeyword): void {
                $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                    ->orWhereRaw("REPLACE(SKcode, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
            })
            ->orderBy('AccountName')
            ->limit(8)
            ->get();
    }

    public function selectInstitution(string $skCode): void
    {
        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $skCode)
            ->first();

        if ($institution === null) {
            return;
        }

        $this->institutionSkCode = trim((string) $institution->SKcode);
        $this->institutionName = $institution->resolvedAccountName();
        $this->csTeam = trim((string) ($institution->accountInfo?->CS ?? ''));
        $this->lockedInstitutionKeyword = $this->institutionName;
        $this->institutionKeyword = $this->institutionName;
    }

    public function selectDetailInstitution(string $skCode): void
    {
        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $skCode)
            ->first();

        if ($institution === null) {
            return;
        }

        $this->detailInstitutionSkCode = trim((string) $institution->SKcode);
        $this->detailInstitutionKeyword = $institution->resolvedAccountName();
        $this->detailCsTeam = trim((string) ($institution->accountInfo?->CS ?? ''));
        $this->lockedDetailInstitutionKeyword = $this->detailInstitutionKeyword;
    }

    public function updatedDetailInstitutionKeyword(): void
    {
        $keyword = trim($this->detailInstitutionKeyword);

        if (blank($keyword)) {
            $this->detailInstitutionSkCode = '';
            $this->detailCsTeam = '';
            $this->lockedDetailInstitutionKeyword = null;

            return;
        }

        if ($this->lockedDetailInstitutionKeyword !== null && $keyword === $this->lockedDetailInstitutionKeyword) {
            return;
        }

        $this->lockedDetailInstitutionKeyword = null;
        $this->detailInstitutionSkCode = '';
    }

    public function updatedInstitutionKeyword(): void
    {
        $keyword = trim($this->institutionKeyword);
        $this->institutionName = $keyword;

        if (blank($keyword)) {
            $this->institutionSkCode = '';
            $this->csTeam = '';
            $this->lockedInstitutionKeyword = null;

            return;
        }

        if ($this->lockedInstitutionKeyword !== null && $keyword === $this->lockedInstitutionKeyword) {
            return;
        }

        $this->lockedInstitutionKeyword = null;
        $this->institutionSkCode = '';
        $this->csTeam = '';
    }

    public function clearInstitutionSelection(): void
    {
        $this->institutionSkCode = '';
        $this->institutionName = '';
        $this->institutionKeyword = '';
        $this->csTeam = '';
        $this->lockedInstitutionKeyword = null;
    }

    public function openCreateModal(): void
    {
        $this->resetFormFields();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetFormFields();
    }

    public function openDetailModal(int $anchorRegistrationId): void
    {
        $this->loadDetailFieldsFromAnchor($anchorRegistrationId);
        $this->detailEditMode = false;
        $this->showDetailModal = true;
    }

    public function startDetailEdit(): void
    {
        $this->detailEditMode = true;
    }

    public function cancelDetailEdit(): void
    {
        if ($this->detailAnchorId === null) {
            return;
        }

        $this->loadDetailFieldsFromAnchor($this->detailAnchorId);
        $this->detailEditMode = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function completeReturnGroup(int $anchorRegistrationId): void
    {
        if (! $this->isCsTeamMenu) {
            return;
        }

        $anchor = StoreReturnRegistration::query()->findOrFail($anchorRegistrationId);
        $items = $this->queryGroupItems($anchor);

        if ($this->isGroupCompleted($items)) {
            return;
        }

        $completedStatus = $this->completedStatus();

        StoreReturnRegistration::query()
            ->whereIn('id', $items->pluck('id'))
            ->update(['status' => $completedStatus]);

        if ($this->showDetailModal && $this->detailAnchorId === $anchor->id) {
            $this->loadDetailFieldsFromAnchor($anchor->id);
            $this->detailEditMode = false;
        }

        session()->flash('success', '반품 처리가 완료되었습니다.');
    }

    public function getIsCsTeamMenuProperty(): bool
    {
        return $this->teamMenu === TeamMenuContext::MENU_CS;
    }

    public function getIsDetailGroupCompletedProperty(): bool
    {
        if ($this->detailItemRows === []) {
            return false;
        }

        return collect($this->detailItemRows)->every(
            fn (array $row): bool => $this->isCompletedStatus((string) ($row['status'] ?? '')),
        );
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->resetDetailFields();
    }

    public function addDetailItemRow(): void
    {
        $this->detailItemRows[] = $this->defaultDetailItemRow();
    }

    public function removeDetailItemRow(int $index): void
    {
        if (count($this->detailItemRows) <= 1) {
            return;
        }

        unset($this->detailItemRows[$index]);
        $this->detailItemRows = array_values($this->detailItemRows);
    }

    public function saveDetail(): void
    {
        if ($this->detailAnchorId === null) {
            return;
        }

        $anchor = StoreReturnRegistration::query()->findOrFail($this->detailAnchorId);
        $originalItems = $this->queryGroupItems($anchor);
        $originalIds = $originalItems->pluck('id');

        $statuses = config('store.return_registration.statuses', []);
        $freightOptions = config('store.return_registration.freight_options', []);
        $detailItemNameRules = $this->itemNameValidationRules(
            collect($this->detailItemRows)->pluck('itemName')->all(),
        );

        $validated = $this->validate([
            'detailReturnDate' => ['required', 'date'],
            'detailInstitutionKeyword' => ['required', 'string', 'max:255'],
            'detailInstitutionSkCode' => ['nullable', 'string', 'max:50'],
            'detailFreight' => ['nullable', 'string', Rule::in($freightOptions)],
            'detailCsTeam' => ['nullable', 'string', 'max:100'],
            'detailItemRows' => ['required', 'array', 'min:1'],
            'detailItemRows.*.id' => ['nullable', 'integer', Rule::exists('store_return_registrations', 'id')],
            'detailItemRows.*.itemName' => $detailItemNameRules,
            'detailItemRows.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'detailItemRows.*.status' => ['required', 'string', Rule::in($statuses)],
            'detailItemRows.*.notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'detailReturnDate.required' => '날짜를 입력해 주세요.',
            'detailReturnDate.date' => '날짜 형식이 올바르지 않습니다.',
            'detailInstitutionKeyword.required' => '기관명을 입력해 주세요.',
            'detailItemRows.required' => '품목을 1개 이상 입력해 주세요.',
            'detailItemRows.min' => '품목을 1개 이상 입력해 주세요.',
            'detailItemRows.*.itemName.required' => '품목명을 선택해 주세요.',
            'detailItemRows.*.itemName.in' => '품목명을 목록에서 선택해 주세요.',
            'detailItemRows.*.quantity.required' => '수량을 입력해 주세요.',
            'detailItemRows.*.quantity.integer' => '수량은 숫자만 입력해 주세요.',
            'detailItemRows.*.quantity.min' => '수량은 1 이상이어야 합니다.',
            'detailItemRows.*.status.required' => '상태를 선택해 주세요.',
            'detailItemRows.*.status.in' => '상태 값이 올바르지 않습니다.',
            'detailFreight.in' => '운임 값이 올바르지 않습니다.',
            'detailItemRows.*.notes.max' => '특이 사항은 2000자 이내로 입력해 주세요.',
        ]);

        $submittedIds = collect($validated['detailItemRows'])
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id);

        if ($submittedIds->diff($originalIds)->isNotEmpty()) {
            $this->addError('detailItemRows', '수정할 수 없는 품목이 포함되어 있습니다.');

            return;
        }

        $institutionSkCode = filled($validated['detailInstitutionSkCode']) ? trim($validated['detailInstitutionSkCode']) : null;
        $institutionName = trim($validated['detailInstitutionKeyword']);
        $freight = filled($validated['detailFreight'] ?? null) ? $validated['detailFreight'] : null;
        $csTeam = filled($validated['detailCsTeam'] ?? null) ? trim($validated['detailCsTeam']) : null;

        $groupPayload = [
            'returned_at' => $validated['detailReturnDate'],
            'institution_sk_code' => $institutionSkCode,
            'institution_name' => $institutionName,
            'freight' => $freight,
            'cs_team' => $csTeam,
        ];

        foreach ($validated['detailItemRows'] as $row) {
            $itemPayload = [
                ...$groupPayload,
                'item_name' => app(StoreReturnEcountProductOptions::class)->displayNameForProductCode(trim($row['itemName'])),
                'quantity' => (int) $row['quantity'],
                'status' => $row['status'],
                'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
            ];

            if (filled($row['id'] ?? null)) {
                StoreReturnRegistration::query()
                    ->whereKey((int) $row['id'])
                    ->update($itemPayload);
            } else {
                StoreReturnRegistration::query()->create([
                    ...$itemPayload,
                    'registered_by' => $anchor->registered_by ?? Auth::id(),
                ]);
            }
        }

        $idsToDelete = $originalIds->diff($submittedIds);
        if ($idsToDelete->isNotEmpty()) {
            StoreReturnRegistration::query()->whereIn('id', $idsToDelete)->delete();
        }

        $this->showDetailModal = false;
        $this->resetDetailFields();
        session()->flash('success', '반품 내역이 수정되었습니다.');
    }

    public function addItemRow(): void
    {
        $this->itemRows[] = $this->defaultItemRow();
    }

    public function removeItemRow(int $index): void
    {
        if (count($this->itemRows) <= 1) {
            return;
        }

        unset($this->itemRows[$index]);
        $this->itemRows = array_values($this->itemRows);
    }

    public function save(): void
    {
        $statuses = config('store.return_registration.statuses', []);
        $freightOptions = config('store.return_registration.freight_options', []);
        $itemNameRules = $this->itemNameValidationRules();

        $validated = $this->validate([
            'returnDate' => ['required', 'date'],
            'institutionKeyword' => ['required', 'string', 'max:255'],
            'institutionSkCode' => ['nullable', 'string', 'max:50'],
            'freight' => ['nullable', 'string', Rule::in($freightOptions)],
            'itemRows' => ['required', 'array', 'min:1'],
            'itemRows.*.itemName' => $itemNameRules,
            'itemRows.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'itemRows.*.status' => ['required', 'string', Rule::in($statuses)],
            'itemRows.*.notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'returnDate.required' => '날짜를 입력해 주세요.',
            'returnDate.date' => '날짜 형식이 올바르지 않습니다.',
            'institutionKeyword.required' => '기관명을 입력해 주세요.',
            'itemRows.required' => '품목을 1개 이상 입력해 주세요.',
            'itemRows.min' => '품목을 1개 이상 입력해 주세요.',
            'itemRows.*.itemName.required' => '품목명을 선택해 주세요.',
            'itemRows.*.itemName.in' => '품목명을 목록에서 선택해 주세요.',
            'itemRows.*.quantity.required' => '수량을 입력해 주세요.',
            'itemRows.*.quantity.integer' => '수량은 숫자만 입력해 주세요.',
            'itemRows.*.quantity.min' => '수량은 1 이상이어야 합니다.',
            'itemRows.*.status.required' => '상태를 선택해 주세요.',
            'itemRows.*.status.in' => '상태 값이 올바르지 않습니다.',
            'freight.in' => '운임 값이 올바르지 않습니다.',
            'itemRows.*.notes.max' => '특이 사항은 2000자 이내로 입력해 주세요.',
        ]);

        $institutionSkCode = filled($validated['institutionSkCode']) ? trim($validated['institutionSkCode']) : null;
        $institutionName = trim($validated['institutionKeyword']);
        $freight = filled($validated['freight'] ?? null) ? $validated['freight'] : null;
        $csTeam = $this->resolveCsTeam($institutionSkCode);
        $userId = Auth::id();
        $savedCount = 0;
        $registrationGroupKey = (string) Str::uuid();

        foreach ($validated['itemRows'] as $row) {
            StoreReturnRegistration::query()->create([
                'returned_at' => $validated['returnDate'],
                'institution_sk_code' => $institutionSkCode,
                'institution_name' => $institutionName,
                'item_name' => app(StoreReturnEcountProductOptions::class)->displayNameForProductCode(trim($row['itemName'])),
                'quantity' => (int) $row['quantity'],
                'status' => $row['status'],
                'freight' => $freight,
                'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                'cs_team' => $csTeam,
                'registered_by' => $userId,
                'registration_group_key' => $registrationGroupKey,
            ]);
            $savedCount++;
        }

        app(StoreReturnTeamsNotifier::class)->notifyRegistered([
            'returned_at' => $validated['returnDate'],
            'institution_name' => $institutionName,
            'institution_sk_code' => $institutionSkCode,
            'freight' => $freight,
            'cs_team' => $csTeam,
            'registrant_name' => Auth::user()?->name ?? '시스템',
            'items' => collect($validated['itemRows'])
                ->map(fn (array $row): array => [
                    'item_name' => app(StoreReturnEcountProductOptions::class)->displayNameForProductCode(trim($row['itemName'])),
                    'quantity' => (int) $row['quantity'],
                    'status' => $row['status'],
                    'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                ])
                ->values()
                ->all(),
        ]);

        $this->resetFormFields();
        $this->showCreateModal = false;
        $this->resetPage();
        session()->flash(
            'success',
            $savedCount > 1 ? "반품 {$savedCount}건이 등록되었습니다." : '반품이 등록되었습니다.',
        );
    }

    public function render()
    {
        $keyword = trim($this->search);

        $registrations = StoreReturnRegistration::query()
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(function (Builder $inner) use ($keyword): void {
                    $inner->where('institution_name', 'like', "%{$keyword}%")
                        ->orWhere('item_name', 'like', "%{$keyword}%")
                        ->orWhere('institution_sk_code', 'like', "%{$keyword}%")
                        ->orWhere('notes', 'like', "%{$keyword}%")
                        ->orWhere('cs_team', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('returned_at')
            ->orderByDesc('id')
            ->get();

        $groups = $this->groupRegistrations($registrations);
        $groups = $this->filterGroupsByDisplayStatus($groups);
        $currentPage = max(1, $this->getPage());

        $registrationGroups = new LengthAwarePaginator(
            $groups->forPage($currentPage, $this->perPage)->values(),
            $groups->count(),
            $this->perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );

        $ecountProductOptions = app(StoreReturnEcountProductOptions::class)->options();

        return view('livewire.store-return-registration-form', [
            'registrationGroups' => $registrationGroups,
            'statusOptions' => config('store.return_registration.statuses', []),
            'freightOptions' => config('store.return_registration.freight_options', []),
            'ecountProductOptions' => $ecountProductOptions,
        ]);
    }

    /**
     * @param  list<string>  $legacyItemNames
     * @return list<string|Rule>
     */
    private function itemNameValidationRules(array $legacyItemNames = []): array
    {
        $rules = ['required', 'string', 'max:255'];

        $allowedValues = app(StoreReturnEcountProductOptions::class)->allowedValues($legacyItemNames);
        if ($allowedValues !== []) {
            $rules[] = Rule::in($allowedValues);
        }

        return $rules;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function filterGroupsByDisplayStatus(Collection $groups): Collection
    {
        return match ($this->statusFilter) {
            'in_progress' => $groups
                ->filter(fn (array $group): bool => ! ($group['is_completed'] ?? false))
                ->values(),
            'completed' => $groups
                ->filter(fn (array $group): bool => (bool) ($group['is_completed'] ?? false))
                ->values(),
            default => $groups,
        };
    }

    /**
     * @param  Collection<int, StoreReturnRegistration>  $registrations
     * @return Collection<int, array<string, mixed>>
     */
    private function groupRegistrations(Collection $registrations): Collection
    {
        $groups = collect();
        $items = $registrations->values();
        $index = 0;

        while ($index < $items->count()) {
            $groupKey = $this->registrationGroupKey($items[$index]);
            $groupItems = collect();

            while (
                $index < $items->count()
                && $this->registrationGroupKey($items[$index]) === $groupKey
            ) {
                $groupItems->push($items[$index]);
                $index++;
            }

            $groups->push($this->buildGroupSummary($groupKey, $groupItems));
        }

        return $groups;
    }

    /**
     * @param  Collection<int, StoreReturnRegistration>  $items
     * @return array<string, mixed>
     */
    private function buildGroupSummary(string $groupKey, Collection $items): array
    {
        $orderedItems = $items->sortBy('id')->values();

        /** @var StoreReturnRegistration $first */
        $first = $orderedItems->first();

        return [
            'key' => $groupKey,
            'anchor_id' => $first->id,
            'returned_at' => $first->returned_at->format('Y-m-d'),
            'institution_name' => $first->institution_name,
            'institution_sk_code' => $first->institution_sk_code,
            'freight' => $first->freight,
            'cs_team' => $first->cs_team,
            'item_summary' => $this->summarizeItemNames($orderedItems),
            'total_quantity' => (int) $orderedItems->sum(fn (StoreReturnRegistration $item): int => $item->quantity ?? 1),
            'status_summary' => $this->displayGroupStatus($orderedItems),
            'is_completed' => $this->isGroupCompleted($orderedItems),
            'notes_summary' => filled($first->notes) ? $first->notes : null,
            'items' => $orderedItems->map(fn (StoreReturnRegistration $item): array => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'quantity' => $item->quantity ?? 1,
                'status' => $item->status,
                'notes' => $item->notes,
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, StoreReturnRegistration>  $items
     */
    private function summarizeItemNames(Collection $items): string
    {
        /** @var StoreReturnRegistration $first */
        $first = $items->first();

        if ($items->count() === 1) {
            return $first->item_name;
        }

        return $first->item_name.' 외 '.($items->count() - 1).'건';
    }

    /**
     * @return Collection<int, StoreReturnRegistration>
     */
    private function queryGroupItems(StoreReturnRegistration $anchor): Collection
    {
        $batchKey = trim((string) ($anchor->registration_group_key ?? ''));
        if ($batchKey !== '') {
            return StoreReturnRegistration::query()
                ->where('registration_group_key', $batchKey)
                ->orderBy('id')
                ->get();
        }

        return StoreReturnRegistration::query()
            ->whereDate('returned_at', $anchor->returned_at)
            ->where('institution_name', $anchor->institution_name)
            ->when(
                filled($anchor->institution_sk_code),
                fn (Builder $query) => $query->where('institution_sk_code', $anchor->institution_sk_code),
                fn (Builder $query) => $query->whereNull('institution_sk_code'),
            )
            ->when(
                filled($anchor->freight),
                fn (Builder $query) => $query->where('freight', $anchor->freight),
                fn (Builder $query) => $query->whereNull('freight'),
            )
            ->when(
                filled($anchor->cs_team),
                fn (Builder $query) => $query->where('cs_team', $anchor->cs_team),
                fn (Builder $query) => $query->whereNull('cs_team'),
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, StoreReturnRegistration>  $items
     */
    private function isGroupCompleted(Collection $items): bool
    {
        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(
            fn (StoreReturnRegistration $item): bool => $this->isCompletedStatus($item->status),
        );
    }

    /**
     * @param  Collection<int, StoreReturnRegistration>  $items
     */
    private function displayGroupStatus(Collection $items): string
    {
        if ($this->isGroupCompleted($items)) {
            return $this->completedStatus();
        }

        return $this->inProgressStatus();
    }

    private function inProgressStatus(): string
    {
        return (string) config('store.return_registration.in_progress_status', '진행 중');
    }

    private function isCompletedStatus(string $status): bool
    {
        return in_array($status, $this->completedStatuses(), true);
    }

    /**
     * @return list<string>
     */
    private function completedStatuses(): array
    {
        return array_values(array_unique(array_filter([
            $this->completedStatus(),
            '입고완료',
        ])));
    }

    private function completedStatus(): string
    {
        return (string) config('store.return_registration.completed_status', '전표 등록 완료');
    }

    /**
     * @return TeamMenuContext::MENU_*|null
     */
    private function resolveTeamMenu(): ?string
    {
        $fromQuery = request()->query('team_menu');
        if (in_array($fromQuery, [
            TeamMenuContext::MENU_CS,
            TeamMenuContext::MENU_COACH,
            TeamMenuContext::MENU_CO,
            TeamMenuContext::MENU_LOGISTICS,
        ], true)) {
            return $fromQuery;
        }

        return TeamMenuContext::activeMenu();
    }

    private function registrationGroupKey(StoreReturnRegistration $registration): string
    {
        $batchKey = trim((string) ($registration->registration_group_key ?? ''));
        if ($batchKey !== '') {
            return 'batch:'.$batchKey;
        }

        return 'legacy:'.implode("\u{1e}", [
            $registration->returned_at->format('Y-m-d'),
            $registration->institution_name,
            (string) ($registration->institution_sk_code ?? ''),
            (string) ($registration->freight ?? ''),
            (string) ($registration->cs_team ?? ''),
        ]);
    }

    private function resolveCsTeam(?string $institutionSkCode): ?string
    {
        if (filled($institutionSkCode)) {
            $institution = Institution::query()
                ->with('accountInfo')
                ->where('SKcode', $institutionSkCode)
                ->first();

            $resolved = trim((string) ($institution?->accountInfo?->CS ?? ''));

            return $resolved !== '' ? $resolved : null;
        }

        $csTeam = trim($this->csTeam);

        return $csTeam !== '' ? $csTeam : null;
    }

    /**
     * @return array{itemName: string, quantity: string, status: string, notes: string}
     */
    private function defaultItemRow(): array
    {
        return [
            'itemName' => '',
            'quantity' => '',
            'status' => (string) (config('store.return_registration.statuses')[0] ?? '정상'),
            'notes' => '',
        ];
    }

    /**
     * @return array{id: null, itemName: string, quantity: string, status: string, notes: string}
     */
    private function defaultDetailItemRow(): array
    {
        return [
            'id' => null,
            'itemName' => '',
            'quantity' => '',
            'status' => (string) (config('store.return_registration.statuses')[0] ?? '정상'),
            'notes' => '',
        ];
    }

    private function resetFormFields(): void
    {
        $this->returnDate = Carbon::now()->format('Y-m-d');
        $this->institutionKeyword = '';
        $this->institutionSkCode = '';
        $this->institutionName = '';
        $this->lockedInstitutionKeyword = null;
        $this->freight = '';
        $this->csTeam = '';
        $this->itemRows = [$this->defaultItemRow()];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    private function resetDetailFields(): void
    {
        $this->detailEditMode = false;
        $this->detailAnchorId = null;
        $this->detailReturnDate = '';
        $this->detailInstitutionKeyword = '';
        $this->detailInstitutionSkCode = '';
        $this->detailFreight = '';
        $this->detailCsTeam = '';
        $this->detailOriginalRegistrationIds = [];
        $this->detailItemRows = [];
        $this->lockedDetailInstitutionKeyword = null;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    private function loadDetailFieldsFromAnchor(int $anchorRegistrationId): void
    {
        $anchor = StoreReturnRegistration::query()->findOrFail($anchorRegistrationId);
        $items = $this->queryGroupItems($anchor)->sortBy('id')->values();

        /** @var StoreReturnRegistration $first */
        $first = $items->first();

        $this->detailAnchorId = $first->id;
        $this->detailReturnDate = $first->returned_at->format('Y-m-d');
        $this->detailInstitutionKeyword = $first->institution_name;
        $this->detailInstitutionSkCode = $first->institution_sk_code ?? '';
        $this->detailFreight = $first->freight ?? '';
        $this->detailCsTeam = $first->cs_team ?? '';
        $this->lockedDetailInstitutionKeyword = filled($first->institution_sk_code)
            ? $first->institution_name
            : null;
        $this->detailOriginalRegistrationIds = $items->pluck('id')->all();
        $this->detailItemRows = $items->map(fn (StoreReturnRegistration $item): array => [
            'id' => $item->id,
            'itemName' => app(StoreReturnEcountProductOptions::class)->selectionValueForStoredItemName($item->item_name),
            'quantity' => (string) ($item->quantity ?? 1),
            'status' => $item->status,
            'notes' => $item->notes ?? '',
        ])->all();
    }
}
