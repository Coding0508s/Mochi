<?php

namespace App\Livewire;

use App\Services\Store\GnuboardStockSyncService;
use App\Services\Store\StoreInventoryApiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class StoreInventoryList extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public string $search = '';

    public int $page = 1;

    public int $perPage = 30;

    public ?string $loadError = null;

    public ?string $saveError = null;

    public ?string $saveSuccess = null;

    public bool $showSkuModal = false;

    public int $skuModalInstance = 0;

    public bool $showDeductDetailModal = false;

    /** @var array<string, mixed> */
    public array $selectedDeductItem = [];

    public bool $showActualStockModal = false;

    public string $actualStockModalProductCode = '';

    public string $actualStockModalProductName = '';

    public int $actualStockModalWarehouseStock = 0;

    public int $actualStockModalCurrentQty = 0;

    public string $actualStockModalNewQty = '';

    public string $actualStockModalMemo = '';

    public string $actualStockModalLastChangedBy = '';

    public string $actualStockModalLastChangedAt = '';

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->loadError = null;
        $this->saveError = null;
        $this->saveSuccess = null;

        try {
            /** @var StoreInventoryApiClient $client */
            $client = app(StoreInventoryApiClient::class);
            $rows = $client->fetchInventory();
            $this->items = $this->hydrateRows($rows);
            $this->page = 1;
        } catch (Throwable $exception) {
            report($exception);
            $this->items = [];
            $this->loadError = '재고 데이터를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.';
        }
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function openSkuModal(): void
    {
        Gate::authorize('manageStoreInventory');

        $this->showSkuModal = true;
        $this->skuModalInstance++;
    }

    public function closeSkuModal(): void
    {
        $this->showSkuModal = false;
    }

    public function openDeductDetail(string $productCode): void
    {
        $target = null;
        foreach ($this->items as $item) {
            if ((string) ($item['product_code'] ?? '') === $productCode) {
                $target = $item;
                break;
            }
        }

        if (! is_array($target)) {
            return;
        }

        $this->selectedDeductItem = $target;
        $this->showDeductDetailModal = true;
    }

    public function closeDeductDetail(): void
    {
        $this->showDeductDetailModal = false;
        $this->selectedDeductItem = [];
    }

    public function openActualStockModal(string $productCode): void
    {
        Gate::authorize('manageStoreInventory');

        $this->saveError = null;
        $this->saveSuccess = null;

        $code = strtoupper(trim($productCode));
        if ($code === '') {
            $this->saveError = '상품코드가 비어 있어 실제수량을 수정할 수 없습니다.';

            return;
        }

        $target = $this->findItemByProductCode($code);
        if (! is_array($target)) {
            $this->saveError = '선택한 품목 정보를 찾지 못했습니다.';

            return;
        }

        $this->actualStockModalProductCode = $code;
        $this->actualStockModalProductName = trim((string) ($target['product_name'] ?? ''));
        $this->actualStockModalWarehouseStock = (int) ($target['warehouse_stock'] ?? 0);
        $this->actualStockModalCurrentQty = (int) ($target['actual_stock_quantity'] ?? 0);
        $this->actualStockModalNewQty = (string) $this->actualStockModalCurrentQty;
        $this->actualStockModalMemo = '';

        $this->actualStockModalLastChangedBy = '';
        $this->actualStockModalLastChangedAt = '';

        /** @var GnuboardStockSyncService $service */
        $service = app(GnuboardStockSyncService::class);
        $latest = $service->getLatestChangeByProductCode($code);
        if (is_array($latest)) {
            $this->actualStockModalLastChangedBy = trim((string) ($latest['changed_by_name'] ?? ''));
            $this->actualStockModalLastChangedAt = trim((string) ($latest['changed_at'] ?? ''));
        }

        $this->showActualStockModal = true;
    }

    public function closeActualStockModal(): void
    {
        $this->showActualStockModal = false;
        $this->actualStockModalProductCode = '';
        $this->actualStockModalProductName = '';
        $this->actualStockModalWarehouseStock = 0;
        $this->actualStockModalCurrentQty = 0;
        $this->actualStockModalNewQty = '';
        $this->actualStockModalMemo = '';
        $this->actualStockModalLastChangedBy = '';
        $this->actualStockModalLastChangedAt = '';
    }

    public function saveActualStockFromModal(): void
    {
        Gate::authorize('manageStoreInventory');

        $this->saveError = null;
        $this->saveSuccess = null;

        $code = strtoupper(trim($this->actualStockModalProductCode));
        $raw = trim($this->actualStockModalNewQty);
        if ($code === '') {
            $this->saveError = '상품코드가 비어 있어 실제수량을 수정할 수 없습니다.';

            return;
        }

        if ($raw === '' || ! is_numeric($raw)) {
            $this->saveError = '실제수량은 숫자로 입력해 주세요.';

            return;
        }

        $newQty = (int) $raw;
        if ($newQty < 0) {
            $this->saveError = '실제수량은 0 이상이어야 합니다.';

            return;
        }

        $this->updateActualStock($code, $newQty, $this->actualStockModalMemo);
        if ($this->saveError === null) {
            $this->actualStockModalCurrentQty = $newQty;
            $this->closeActualStockModal();
        }
    }

    public function updateActualStock(string $productCode, mixed $qty, ?string $memo = null): void
    {
        $raw = is_scalar($qty) ? trim((string) $qty) : '';
        if ($raw === '' || ! is_numeric($raw)) {
            $this->saveError = '실제수량은 숫자로 입력해 주세요.';

            return;
        }

        $newQty = (int) $raw;
        if ($newQty < 0) {
            $this->saveError = '실제수량은 0 이상이어야 합니다.';

            return;
        }

        try {
            /** @var GnuboardStockSyncService $service */
            $service = app(GnuboardStockSyncService::class);
            $updated = $service->updateActualStockQuantity($productCode, $newQty, auth()->id(), $memo);
            $code = strtoupper(trim((string) ($updated['product_code'] ?? $productCode)));
            $afterQty = (int) ($updated['after_qty'] ?? $newQty);

            foreach ($this->items as &$item) {
                if (strtoupper((string) ($item['product_code'] ?? '')) === $code) {
                    $item['actual_stock_quantity'] = $afterQty;
                    break;
                }
            }
            unset($item);

            $this->saveSuccess = "실제수량을 저장했습니다. ({$code}: ".number_format($afterQty).')';
        } catch (ValidationException $exception) {
            $this->saveError = $exception->getMessage();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveError = '실제수량 저장에 실패했습니다. 잠시 후 다시 시도해 주세요.';
        }
    }

    #[On('store-inventory-skus-updated')]
    public function refreshAfterSkuUpdated(): void
    {
        $this->refresh();
    }

    public function nextPage(): void
    {
        if ($this->page < $this->getLastPageProperty()) {
            $this->page++;
        }
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredItemsProperty(): array
    {
        $keyword = mb_strtolower(trim($this->search));
        if ($keyword === '') {
            return $this->items;
        }

        return array_values(array_filter($this->items, function (array $item) use ($keyword): bool {
            $code = mb_strtolower((string) ($item['product_code'] ?? ''));
            $name = mb_strtolower((string) ($item['product_name'] ?? ''));

            return str_contains($code, $keyword) || str_contains($name, $keyword);
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaginatedItemsProperty(): array
    {
        $offset = max(0, ($this->page - 1) * $this->perPage);

        return array_slice($this->filteredItems, $offset, $this->perPage);
    }

    public function getTotalItemsProperty(): int
    {
        return count($this->filteredItems);
    }

    public function getLastPageProperty(): int
    {
        if ($this->totalItems <= 0) {
            return 1;
        }

        return (int) ceil($this->totalItems / $this->perPage);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $code = (string) ($row['product_code'] ?? '');
            $row['row_key'] = $this->rowKeyFromCode($code);
            $row['last_deduct_qty'] = isset($row['last_deduct_qty']) && is_numeric($row['last_deduct_qty'])
                ? (int) $row['last_deduct_qty']
                : null;
            $row['last_deduct_at_display'] = $this->formatDateTime((string) ($row['last_deduct_at'] ?? ''));
            $row['last_deduct_type'] = trim((string) ($row['last_deduct_type'] ?? ''));
            $row['last_deduct_reason'] = trim((string) ($row['last_deduct_reason'] ?? ''));
            $row['last_deduct_ref'] = trim((string) ($row['last_deduct_ref'] ?? ''));
            $row['notify_quantity'] = isset($row['notify_quantity']) && is_numeric($row['notify_quantity'])
                ? max(0, (int) $row['notify_quantity'])
                : 0;
            $row['actual_stock_quantity'] = isset($row['actual_stock_quantity']) && is_numeric($row['actual_stock_quantity'])
                ? max(0, (int) $row['actual_stock_quantity'])
                : 0;

            return $row;
        }, $rows);
    }

    private function rowKeyFromCode(string $productCode): string
    {
        if ($productCode === '') {
            return '';
        }

        return 'row_'.md5($productCode);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findItemByProductCode(string $productCode): ?array
    {
        $code = strtoupper(trim($productCode));
        foreach ($this->items as $item) {
            if (strtoupper((string) ($item['product_code'] ?? '')) === $code) {
                return $item;
            }
        }

        return null;
    }

    private function formatDateTime(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '-';
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d H:i');
        } catch (Throwable) {
            return $raw;
        }
    }

    public function render()
    {
        return view('livewire.store-inventory-list', [
            'paginatedItems' => $this->paginatedItems,
        ]);
    }
}
