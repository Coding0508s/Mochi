<?php

namespace App\Livewire;

use App\Models\StoreInventorySku;
use App\Repositories\GrapeSeed\GnuboardShopItemRepository;
use App\Repositories\Store\StoreInventorySkuRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class StoreInventorySkuManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $newProdCd = '';

    public string $newMemo = '';

    public int $newSortOrder = 0;

    public string $bulkProdCodes = '';

    public int $bulkSortOrderStart = 0;

    public function mount(): void
    {
        Gate::authorize('manageStoreInventory');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function addSku(): void
    {
        Gate::authorize('manageStoreInventory');

        $validated = $this->validate([
            'newProdCd' => ['required', 'string', 'max:40', Rule::unique('store_inventory_skus', 'prod_cd')],
            'newMemo' => ['nullable', 'string', 'max:255'],
            'newSortOrder' => ['required', 'integer', 'min:0'],
        ], [
            'newProdCd.required' => '품목코드는 필수입니다.',
            'newProdCd.unique' => '이미 등록된 품목코드입니다.',
        ]);

        app(StoreInventorySkuRepository::class)->create(
            prodCd: $validated['newProdCd'],
            isActive: true,
            sortOrder: (int) $validated['newSortOrder'],
            memo: $validated['newMemo'] !== '' ? $validated['newMemo'] : null
        );

        $this->newProdCd = '';
        $this->newMemo = '';
        $this->newSortOrder = 0;
        session()->flash('success', '재고 연동 품목이 추가되었습니다.');
        $this->resetPage();
        $this->dispatch('store-inventory-skus-updated');
    }

    public function bulkAddSkus(): void
    {
        Gate::authorize('manageStoreInventory');

        $validated = $this->validate([
            'bulkProdCodes' => ['required', 'string'],
            'bulkSortOrderStart' => ['required', 'integer', 'min:0'],
        ], [
            'bulkProdCodes.required' => '일괄 등록할 품목코드를 입력해 주세요.',
        ]);

        $codes = $this->parseBulkProdCodes($validated['bulkProdCodes']);
        if ($codes === []) {
            $this->addError('bulkProdCodes', '유효한 품목코드가 없습니다.');

            return;
        }

        $existing = StoreInventorySku::query()
            ->whereIn('prod_cd', $codes)
            ->pluck('prod_cd')
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->all();
        $existingSet = array_flip($existing);

        $inserted = 0;
        $duplicated = 0;
        $sortOrder = (int) $validated['bulkSortOrderStart'];

        foreach ($codes as $code) {
            if (isset($existingSet[$code])) {
                $duplicated++;

                continue;
            }

            app(StoreInventorySkuRepository::class)->create(
                prodCd: $code,
                isActive: true,
                sortOrder: $sortOrder,
                memo: null
            );
            $sortOrder++;
            $inserted++;
        }

        $this->bulkProdCodes = '';
        session()->flash('success', "일괄 등록 완료: 추가 {$inserted}건, 중복 {$duplicated}건");
        $this->resetPage();
        $this->dispatch('store-inventory-skus-updated');
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('manageStoreInventory');

        $sku = StoreInventorySku::query()->find($id);
        if (! $sku) {
            return;
        }

        app(StoreInventorySkuRepository::class)->update($sku, [
            'is_active' => ! $sku->is_active,
        ]);
        $this->dispatch('store-inventory-skus-updated');
    }

    public function updateSortOrder(int $id, int $sortOrder): void
    {
        Gate::authorize('manageStoreInventory');

        $sku = StoreInventorySku::query()->find($id);
        if (! $sku) {
            return;
        }

        app(StoreInventorySkuRepository::class)->update($sku, [
            'sort_order' => max(0, $sortOrder),
        ]);
        $this->dispatch('store-inventory-skus-updated');
    }

    public function updateMemo(int $id, string $memo): void
    {
        Gate::authorize('manageStoreInventory');

        $sku = StoreInventorySku::query()->find($id);
        if (! $sku) {
            return;
        }

        app(StoreInventorySkuRepository::class)->update($sku, [
            'memo' => mb_substr(trim($memo), 0, 255),
        ]);
        $this->dispatch('store-inventory-skus-updated');
    }

    public function render()
    {
        $skus = app(StoreInventorySkuRepository::class)->paginate($this->search, 20);

        $productNamesBySkuId = $this->resolveProductNamesForSkus($skus);

        return view('livewire.store-inventory-sku-manager', [
            'skus' => $skus,
            'productNamesBySkuId' => $productNamesBySkuId,
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, StoreInventorySku>  $skus
     * @return array<int, string>
     */
    private function resolveProductNamesForSkus($skus): array
    {
        $codes = [];
        foreach ($skus as $sku) {
            $codes[] = (string) $sku->prod_cd;
        }
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $c): string => strtoupper(trim($c)),
            $codes
        ), static fn (string $c): bool => $c !== '')));

        $nameMap = [];
        if ($codes !== []) {
            try {
                $nameMap = app(GnuboardShopItemRepository::class)->getProductNameMapByProductCodes($codes);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $byId = [];
        foreach ($skus as $sku) {
            $key = $this->normalizedProdCdKey((string) $sku->prod_cd);
            $name = trim((string) ($nameMap[$key] ?? ''));
            $byId[(int) $sku->id] = $name !== '' ? $name : '-';
        }

        return $byId;
    }

    private function normalizedProdCdKey(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/^[\p{Zs}]+|[\p{Zs}]+$/u', '', $code) ?? '';

        return $code;
    }

    /**
     * @return array<int, string>
     */
    private function parseBulkProdCodes(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', strtoupper(trim($raw)), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return [];
        }

        $codes = [];
        foreach ($parts as $part) {
            $code = trim($part);
            if ($code === '' || mb_strlen($code) > 40) {
                continue;
            }
            $codes[] = $code;
        }

        return array_values(array_unique($codes));
    }
}
