<?php

namespace App\Livewire;

use App\Models\Institution;
use App\Models\StoreReturnRegistration;
use App\Services\Store\EcountApiClient;
use App\Support\StoreReturnEcountProductOptions;
use App\Support\StoreReturnSaleOrderPayloadBuilder;
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
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class StoreReturnRegistrationForm extends Component
{
    use WithPagination;

    public string $returnDate = '';

    /**
     * @var array<int, array{
     *     institutionKeyword: string,
     *     institutionSkCode: string,
     *     freight: string,
     *     csTeam: string,
     *     itemRows: array<int, array{itemName: string, quantity: string, status: string, notes: string}>
     * }>
     */
    public array $institutionBlocks = [];

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

    public string $detailShippingAddress = '';

    public ?string $detailEcountSlipNo = null;

    /**
     * @var array<int, int>
     */
    public array $detailOriginalRegistrationIds = [];

    /**
     * @var array<int, array{id: int|null, itemName: string, quantity: string, status: string, notes: string, className: string, ecountRemarks: string}>
     */
    public array $detailItemRows = [];

    public ?string $teamMenu = null;

    private ?string $lockedDetailInstitutionKeyword = null;

    /**
     * @var array<int, string|null>
     */
    private array $lockedInstitutionKeywords = [];

    public function mount(): void
    {
        $this->returnDate = Carbon::now()->format('Y-m-d');
        $this->institutionBlocks = [$this->defaultInstitutionBlock()];
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
    public function institutionSuggestionsFor(int $blockIndex): Collection
    {
        $block = $this->institutionBlocks[$blockIndex] ?? null;
        if (! is_array($block)) {
            return collect();
        }

        if (filled($block['institutionSkCode'] ?? '')) {
            return collect();
        }

        $keyword = trim((string) ($block['institutionKeyword'] ?? ''));
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

    public function selectInstitution(int $blockIndex, string $skCode): void
    {
        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $skCode)
            ->first();

        if ($institution === null || ! isset($this->institutionBlocks[$blockIndex])) {
            return;
        }

        $institutionName = $institution->resolvedAccountName();

        $this->institutionBlocks[$blockIndex]['institutionSkCode'] = trim((string) $institution->SKcode);
        $this->institutionBlocks[$blockIndex]['csTeam'] = trim((string) ($institution->accountInfo?->CS ?? ''));
        $this->institutionBlocks[$blockIndex]['institutionKeyword'] = $institutionName;
        $this->lockedInstitutionKeywords[$blockIndex] = $institutionName;
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

    public function updatedInstitutionBlocks(mixed $value, string $name): void
    {
        if (! preg_match('/^(\d+)\.institutionKeyword$/', $name, $matches)) {
            return;
        }

        $blockIndex = (int) $matches[1];
        if (! isset($this->institutionBlocks[$blockIndex])) {
            return;
        }

        $keyword = trim((string) ($this->institutionBlocks[$blockIndex]['institutionKeyword'] ?? ''));

        if (blank($keyword)) {
            $this->institutionBlocks[$blockIndex]['institutionSkCode'] = '';
            $this->institutionBlocks[$blockIndex]['csTeam'] = '';
            $this->lockedInstitutionKeywords[$blockIndex] = null;

            return;
        }

        $lockedKeyword = $this->lockedInstitutionKeywords[$blockIndex] ?? null;
        if ($lockedKeyword !== null && $keyword === $lockedKeyword) {
            return;
        }

        $this->lockedInstitutionKeywords[$blockIndex] = null;
        $this->institutionBlocks[$blockIndex]['institutionSkCode'] = '';
        $this->institutionBlocks[$blockIndex]['csTeam'] = '';
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

        /** @var StoreReturnRegistration $first */
        $first = $items->first();
        $productOptions = app(StoreReturnEcountProductOptions::class);

        app(StoreReturnTeamsNotifier::class)->notifyCompleted([
            'returned_at' => $first->returned_at->format('Y-m-d'),
            'institution_name' => $first->institution_name,
            'institution_sk_code' => $first->institution_sk_code,
            'freight' => $first->freight,
            'cs_team' => $first->cs_team,
            'completed_by_name' => Auth::user()?->nameForCoReports() ?? '시스템',
            'items' => $items->map(fn (StoreReturnRegistration $item): array => [
                'item_name' => $productOptions->displayNameForStoredItemName($item->item_name),
                'quantity' => $item->quantity ?? 1,
                'status' => $completedStatus,
                'notes' => $item->notes,
            ])->all(),
        ]);

        if ($this->showDetailModal && $this->detailAnchorId === $anchor->id) {
            $this->loadDetailFieldsFromAnchor($anchor->id);
            $this->detailEditMode = false;
        }

        session()->flash('success', '반품 처리가 완료되었습니다.');
    }

    public function createEcountSaleOrder(int $anchorRegistrationId): void
    {
        if (! $this->isCsTeamMenu) {
            abort(403);
        }

        if (! config('store.return_registration.sale_order_enabled')) {
            session()->flash('error', 'Ecount 주문서 생성 기능이 비활성화되어 있습니다.');

            return;
        }

        $anchor = StoreReturnRegistration::query()->findOrFail($anchorRegistrationId);
        $items = $this->queryGroupItems($anchor);

        if ($items->contains(fn (StoreReturnRegistration $item): bool => filled($item->ecount_slip_no))) {
            session()->flash('error', '이미 생성된 주문서가 있습니다.');

            return;
        }

        try {
            $payload = app(StoreReturnSaleOrderPayloadBuilder::class)->build($items);
            $result = app(EcountApiClient::class)->saveSaleOrder($payload);
        } catch (InvalidArgumentException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        } catch (RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        $slipNos = $result['slip_nos'];
        $slipNo = count($slipNos) === 1 ? $slipNos[0] : implode(',', $slipNos);

        StoreReturnRegistration::query()
            ->whereIn('id', $items->pluck('id'))
            ->update([
                'ecount_slip_no' => $slipNo,
                'ecount_order_synced_at' => now(),
            ]);

        if (
            $this->showDetailModal
            && $this->detailAnchorId !== null
            && $items->pluck('id')->contains($this->detailAnchorId)
        ) {
            $this->loadDetailFieldsFromAnchor($this->detailAnchorId);
        }

        session()->flash('success', 'Ecount 주문서가 생성되었습니다.');
    }

    public function deleteReturnGroup(int $anchorRegistrationId): void
    {
        if (! Auth::user()?->hasFullAccess()) {
            abort(403);
        }

        $anchor = StoreReturnRegistration::query()->findOrFail($anchorRegistrationId);
        $items = $this->queryGroupItems($anchor);

        StoreReturnRegistration::query()
            ->whereIn('id', $items->pluck('id'))
            ->delete();

        if ($this->showDetailModal && $this->detailAnchorId === $anchor->id) {
            $this->closeDetailModal();
        }

        session()->flash('success', '반품 내역이 삭제되었습니다.');
    }

    public function getIsCsTeamMenuProperty(): bool
    {
        return $this->teamMenu === TeamMenuContext::MENU_CS;
    }

    public function getCanDeleteReturnGroupsProperty(): bool
    {
        return (bool) Auth::user()?->hasFullAccess();
    }

    public function getListTableColumnCountProperty(): int
    {
        $count = 8;

        if ($this->isCsTeamMenu) {
            $count++;
        }

        if ($this->canDeleteReturnGroups) {
            $count++;
        }

        return $count;
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
            'detailShippingAddress' => ['nullable', 'string', 'max:500'],
            'detailItemRows' => ['required', 'array', 'min:1'],
            'detailItemRows.*.id' => ['nullable', 'integer', Rule::exists('store_return_registrations', 'id')],
            'detailItemRows.*.itemName' => $detailItemNameRules,
            'detailItemRows.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'detailItemRows.*.status' => ['required', 'string', Rule::in($statuses)],
            'detailItemRows.*.notes' => ['nullable', 'string', 'max:2000'],
            'detailItemRows.*.className' => ['nullable', 'string', 'max:100'],
            'detailItemRows.*.ecountRemarks' => ['nullable', 'string', 'max:255'],
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
            'detailShippingAddress.max' => '배송지는 500자 이내로 입력해 주세요.',
            'detailItemRows.*.className.max' => 'Class Name은 100자 이내로 입력해 주세요.',
            'detailItemRows.*.ecountRemarks.max' => 'Ecount 적요는 255자 이내로 입력해 주세요.',
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
        $shippingAddress = filled($validated['detailShippingAddress'] ?? null)
            ? trim($validated['detailShippingAddress'])
            : null;

        $groupPayload = [
            'returned_at' => $validated['detailReturnDate'],
            'institution_sk_code' => $institutionSkCode,
            'institution_name' => $institutionName,
            'freight' => $freight,
            'cs_team' => $csTeam,
            'shipping_address' => $shippingAddress,
        ];

        foreach ($validated['detailItemRows'] as $row) {
            $itemPayload = [
                ...$groupPayload,
                'item_name' => app(StoreReturnEcountProductOptions::class)->displayNameForProductCode(trim($row['itemName'])),
                'quantity' => (int) $row['quantity'],
                'status' => $row['status'],
                'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                'class_name' => filled($row['className'] ?? null) ? trim((string) $row['className']) : null,
                'ecount_remarks' => filled($row['ecountRemarks'] ?? null) ? trim((string) $row['ecountRemarks']) : null,
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

    public function addItemRow(?int $blockIndex = null): void
    {
        $blockIndex ??= count($this->institutionBlocks) - 1;

        if (! isset($this->institutionBlocks[$blockIndex])) {
            return;
        }

        $this->institutionBlocks[$blockIndex]['itemRows'][] = $this->defaultItemRow();
    }

    public function addInstitutionBlock(): void
    {
        $this->institutionBlocks[] = $this->defaultInstitutionBlock();
    }

    public function removeItemRow(int $blockIndex, int $itemIndex): void
    {
        if (! isset($this->institutionBlocks[$blockIndex]['itemRows'][$itemIndex])) {
            return;
        }

        if (count($this->institutionBlocks[$blockIndex]['itemRows']) <= 1) {
            return;
        }

        unset($this->institutionBlocks[$blockIndex]['itemRows'][$itemIndex]);
        $this->institutionBlocks[$blockIndex]['itemRows'] = array_values($this->institutionBlocks[$blockIndex]['itemRows']);
    }

    public function removeInstitutionBlock(int $blockIndex): void
    {
        if (count($this->institutionBlocks) <= 1) {
            return;
        }

        unset($this->institutionBlocks[$blockIndex]);
        $this->reindexInstitutionBlocks();
    }

    private function reindexInstitutionBlocks(): void
    {
        $lockedKeywords = [];

        foreach (array_values($this->institutionBlocks) as $index => $block) {
            if (filled($block['institutionSkCode'] ?? '') && filled($block['institutionKeyword'] ?? '')) {
                $lockedKeywords[$index] = (string) $block['institutionKeyword'];
            }
        }

        $this->institutionBlocks = array_values($this->institutionBlocks);
        $this->lockedInstitutionKeywords = $lockedKeywords;
    }

    public function save(): void
    {
        $statuses = config('store.return_registration.statuses', []);
        $freightOptions = config('store.return_registration.freight_options', []);
        $legacyItemNames = collect($this->institutionBlocks)
            ->flatMap(fn (array $block): Collection => collect($block['itemRows'])->pluck('itemName'))
            ->all();
        $itemNameRules = $this->itemNameValidationRules($legacyItemNames);

        $validated = $this->validate([
            'returnDate' => ['required', 'date'],
            'institutionBlocks' => ['required', 'array', 'min:1'],
            'institutionBlocks.*.institutionKeyword' => ['required', 'string', 'max:255'],
            'institutionBlocks.*.institutionSkCode' => ['nullable', 'string', 'max:50'],
            'institutionBlocks.*.freight' => ['nullable', 'string', Rule::in($freightOptions)],
            'institutionBlocks.*.itemRows' => ['required', 'array', 'min:1'],
            'institutionBlocks.*.itemRows.*.itemName' => $itemNameRules,
            'institutionBlocks.*.itemRows.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'institutionBlocks.*.itemRows.*.status' => ['required', 'string', Rule::in($statuses)],
            'institutionBlocks.*.itemRows.*.notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'returnDate.required' => '날짜를 입력해 주세요.',
            'returnDate.date' => '날짜 형식이 올바르지 않습니다.',
            'institutionBlocks.required' => '기관을 1개 이상 입력해 주세요.',
            'institutionBlocks.min' => '기관을 1개 이상 입력해 주세요.',
            'institutionBlocks.*.institutionKeyword.required' => '기관명을 입력해 주세요.',
            'institutionBlocks.*.itemRows.required' => '품목을 1개 이상 입력해 주세요.',
            'institutionBlocks.*.itemRows.min' => '품목을 1개 이상 입력해 주세요.',
            'institutionBlocks.*.itemRows.*.itemName.required' => '품목명을 선택해 주세요.',
            'institutionBlocks.*.itemRows.*.itemName.in' => '품목명을 목록에서 선택해 주세요.',
            'institutionBlocks.*.itemRows.*.quantity.required' => '수량을 입력해 주세요.',
            'institutionBlocks.*.itemRows.*.quantity.integer' => '수량은 숫자만 입력해 주세요.',
            'institutionBlocks.*.itemRows.*.quantity.min' => '수량은 1 이상이어야 합니다.',
            'institutionBlocks.*.itemRows.*.status.required' => '상태를 선택해 주세요.',
            'institutionBlocks.*.itemRows.*.status.in' => '상태 값이 올바르지 않습니다.',
            'institutionBlocks.*.freight.in' => '운임 값이 올바르지 않습니다.',
            'institutionBlocks.*.itemRows.*.notes.max' => '특이 사항은 2000자 이내로 입력해 주세요.',
        ]);

        $userId = Auth::id();
        $savedCount = 0;
        $institutionCount = count($validated['institutionBlocks']);
        $productOptions = app(StoreReturnEcountProductOptions::class);

        foreach ($validated['institutionBlocks'] as $block) {
            $institutionSkCode = filled($block['institutionSkCode'] ?? null) ? trim((string) $block['institutionSkCode']) : null;
            $institutionName = trim((string) $block['institutionKeyword']);
            $freight = filled($block['freight'] ?? null) ? $block['freight'] : null;
            $csTeam = $this->resolveCsTeam($institutionSkCode, (string) ($block['csTeam'] ?? ''));
            $registrationGroupKey = (string) Str::uuid();

            foreach ($block['itemRows'] as $row) {
                StoreReturnRegistration::query()->create([
                    'returned_at' => $validated['returnDate'],
                    'institution_sk_code' => $institutionSkCode,
                    'institution_name' => $institutionName,
                    'item_name' => $productOptions->displayNameForProductCode(trim((string) $row['itemName'])),
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
        }

        app(StoreReturnTeamsNotifier::class)->notifyRegistrationSaved(
            registrantName: Auth::user()?->nameForCoReports() ?? '시스템',
            returnedAt: $validated['returnDate'],
            savedCount: $savedCount,
            institutionCount: $institutionCount,
        );

        $this->resetFormFields();
        $this->showCreateModal = false;
        $this->resetPage();

        $successMessage = match (true) {
            $institutionCount > 1 && $savedCount > 1 => "반품 {$savedCount}건({$institutionCount}개 기관)이 등록되었습니다.",
            $savedCount > 1 => "반품 {$savedCount}건이 등록되었습니다.",
            $institutionCount > 1 => "반품 {$institutionCount}개 기관이 등록되었습니다.",
            default => '반품이 등록되었습니다.',
        };

        session()->flash('success', $successMessage);
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
            'ecount_slip_no' => $first->ecount_slip_no,
            'item_summary' => $this->summarizeItemNames($orderedItems),
            'total_quantity' => (int) $orderedItems->sum(fn (StoreReturnRegistration $item): int => $item->quantity ?? 1),
            'status_summary' => $this->displayGroupStatus($orderedItems),
            'is_completed' => $this->isGroupCompleted($orderedItems),
            'notes_summary' => filled($first->notes) ? $first->notes : null,
            'items' => $orderedItems->map(fn (StoreReturnRegistration $item): array => [
                'id' => $item->id,
                'item_name' => app(StoreReturnEcountProductOptions::class)->displayNameForStoredItemName($item->item_name),
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
        $productOptions = app(StoreReturnEcountProductOptions::class);

        /** @var StoreReturnRegistration $first */
        $first = $items->first();
        $firstDisplayName = $productOptions->displayNameForStoredItemName($first->item_name);

        if ($items->count() === 1) {
            return $firstDisplayName;
        }

        return $firstDisplayName.' 외 '.($items->count() - 1).'건';
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

    private function resolveCsTeam(?string $institutionSkCode, string $fallbackCsTeam = ''): ?string
    {
        if (filled($institutionSkCode)) {
            $institution = Institution::query()
                ->with('accountInfo')
                ->where('SKcode', $institutionSkCode)
                ->first();

            $resolved = trim((string) ($institution?->accountInfo?->CS ?? ''));

            return $resolved !== '' ? $resolved : null;
        }

        $csTeam = trim($fallbackCsTeam);

        return $csTeam !== '' ? $csTeam : null;
    }

    /**
     * @return array{
     *     institutionKeyword: string,
     *     institutionSkCode: string,
     *     freight: string,
     *     csTeam: string,
     *     itemRows: array<int, array{itemName: string, quantity: string, status: string, notes: string}>
     * }
     */
    private function defaultInstitutionBlock(): array
    {
        return [
            'institutionKeyword' => '',
            'institutionSkCode' => '',
            'freight' => '',
            'csTeam' => '',
            'itemRows' => [$this->defaultItemRow()],
        ];
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
     * @return array{id: null, itemName: string, quantity: string, status: string, notes: string, className: string, ecountRemarks: string}
     */
    private function defaultDetailItemRow(): array
    {
        return [
            'id' => null,
            'itemName' => '',
            'quantity' => '',
            'status' => (string) (config('store.return_registration.statuses')[0] ?? '정상'),
            'notes' => '',
            'className' => '',
            'ecountRemarks' => '',
        ];
    }

    private function resetFormFields(): void
    {
        $this->returnDate = Carbon::now()->format('Y-m-d');
        $this->institutionBlocks = [$this->defaultInstitutionBlock()];
        $this->lockedInstitutionKeywords = [];
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
        $this->detailShippingAddress = '';
        $this->detailEcountSlipNo = null;
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
        $this->detailShippingAddress = $first->shipping_address ?? '';
        $this->detailEcountSlipNo = $first->ecount_slip_no;
        $this->lockedDetailInstitutionKeyword = filled($first->institution_sk_code)
            ? $first->institution_name
            : null;
        $this->detailOriginalRegistrationIds = $items->pluck('id')->all();
        $productOptions = app(StoreReturnEcountProductOptions::class);

        $this->detailItemRows = $items->map(fn (StoreReturnRegistration $item): array => [
            'id' => $item->id,
            'itemName' => $productOptions->selectionValueForStoredItemName($item->item_name),
            'itemDisplayName' => $productOptions->displayNameForStoredItemName($item->item_name),
            'quantity' => (string) ($item->quantity ?? 1),
            'status' => $item->status,
            'notes' => $item->notes ?? '',
            'className' => $item->class_name ?? '',
            'ecountRemarks' => $item->ecount_remarks ?? '',
        ])->all();
    }
}
