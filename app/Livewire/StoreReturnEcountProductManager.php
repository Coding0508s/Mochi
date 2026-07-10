<?php

namespace App\Livewire;

use App\Models\StoreReturnEcountProduct;
use App\Repositories\Store\StoreReturnEcountProductRepository;
use App\Support\StoreReturnEcountProductOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class StoreReturnEcountProductManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $newProdCd = '';

    public string $newMemo = '';

    public string $bulkProdCodes = '';

    public function mount(): void
    {
        Gate::authorize('manageStoreReturnProducts');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function addProduct(): void
    {
        Gate::authorize('manageStoreReturnProducts');

        $validated = $this->validate([
            'newProdCd' => ['required', 'string', 'max:40', Rule::unique('store_return_ecount_products', 'prod_cd')],
            'newMemo' => ['nullable', 'string', 'max:255'],
        ], [
            'newProdCd.required' => '품목코드는 필수입니다.',
            'newProdCd.unique' => '이미 등록된 품목코드입니다.',
        ]);

        $productName = $this->resolveProductNameForCode($validated['newProdCd']);

        app(StoreReturnEcountProductRepository::class)->create(
            prodCd: $validated['newProdCd'],
            isActive: true,
            sortOrder: 0,
            memo: $validated['newMemo'] !== '' ? $validated['newMemo'] : null,
            productName: $productName,
        );

        $this->newProdCd = '';
        $this->newMemo = '';
        $this->forgetProductOptionsCache();
        session()->flash('success', '반품 등록 품목이 추가되었습니다.');
        $this->resetPage();
    }

    public function bulkAddProducts(): void
    {
        Gate::authorize('manageStoreReturnProducts');

        $validated = $this->validate([
            'bulkProdCodes' => ['required', 'string'],
        ], [
            'bulkProdCodes.required' => '일괄 등록할 품목코드를 입력해 주세요.',
        ]);

        $codes = $this->parseBulkProdCodes($validated['bulkProdCodes']);
        if ($codes === []) {
            $this->addError('bulkProdCodes', '유효한 품목코드가 없습니다.');

            return;
        }

        $existing = StoreReturnEcountProduct::query()
            ->whereIn('prod_cd', $codes)
            ->pluck('prod_cd')
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->all();
        $existingSet = array_flip($existing);

        $inserted = 0;
        $duplicated = 0;
        $repository = app(StoreReturnEcountProductRepository::class);
        $nameMap = app(StoreReturnEcountProductOptions::class)->resolveDisplayNamesForCodes($codes);

        foreach ($codes as $code) {
            if (isset($existingSet[$code])) {
                $duplicated++;

                continue;
            }

            $repository->create(
                prodCd: $code,
                isActive: true,
                sortOrder: 0,
                memo: null,
                productName: trim((string) ($nameMap[$code] ?? '')) ?: null,
            );
            $inserted++;
        }

        $this->bulkProdCodes = '';
        $this->forgetProductOptionsCache();
        session()->flash('success', "일괄 등록 완료: 추가 {$inserted}건, 중복 {$duplicated}건");
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('manageStoreReturnProducts');

        $product = StoreReturnEcountProduct::query()->find($id);
        if (! $product) {
            return;
        }

        app(StoreReturnEcountProductRepository::class)->update($product, [
            'is_active' => ! $product->is_active,
        ]);

        $this->forgetProductOptionsCache();
    }

    public function deleteProduct(int $id): void
    {
        Gate::authorize('manageStoreReturnProducts');

        $product = StoreReturnEcountProduct::query()->find($id);
        if (! $product) {
            return;
        }

        app(StoreReturnEcountProductRepository::class)->delete($product);

        $this->forgetProductOptionsCache();
        session()->flash('success', '반품 등록 품목 목록에서 제거했습니다. 이카운트 품목 자체는 삭제되지 않습니다.');
        $this->resetPage();
    }

    public function updateMemo(int $id, string $memo): void
    {
        Gate::authorize('manageStoreReturnProducts');

        $product = StoreReturnEcountProduct::query()->find($id);
        if (! $product) {
            return;
        }

        app(StoreReturnEcountProductRepository::class)->update($product, [
            'memo' => mb_substr(trim($memo), 0, 255),
        ]);
    }

    public function render()
    {
        $products = app(StoreReturnEcountProductRepository::class)->paginate($this->search, 20);
        $productNamesById = $this->resolveProductNames($products->getCollection()->pluck('prod_cd')->all(), $products);

        return view('livewire.store-return-ecount-product-manager', [
            'products' => $products,
            'productNamesById' => $productNamesById,
        ]);
    }

    /**
     * @param  array<int, string>  $productCodes
     * @param  LengthAwarePaginator<int, StoreReturnEcountProduct>  $products
     * @return array<int, string>
     */
    private function resolveProductNames(array $productCodes, $products): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            $productCodes,
        ), static fn (string $code): bool => $code !== '')));

        $nameMap = $codes !== []
            ? app(StoreReturnEcountProductOptions::class)->resolveDisplayNamesForCodes($codes)
            : [];

        $resolved = [];
        foreach ($products as $product) {
            $code = strtoupper(trim((string) $product->prod_cd));
            $cachedName = trim((string) ($product->product_name ?? ''));
            $resolved[$product->id] = $cachedName !== ''
                ? $cachedName
                : (trim((string) ($nameMap[$code] ?? '')) ?: '-');
        }

        return $resolved;
    }

    private function resolveProductNameForCode(string $prodCd): ?string
    {
        $code = strtoupper(trim($prodCd));
        if ($code === '') {
            return null;
        }

        $name = trim((string) (app(StoreReturnEcountProductOptions::class)->resolveDisplayNamesForCodes([$code])[$code] ?? ''));

        return $name !== '' ? $name : null;
    }

    private function forgetProductOptionsCache(): void
    {
        app(StoreReturnEcountProductOptions::class)->forgetCachedOptions();
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
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }
}
