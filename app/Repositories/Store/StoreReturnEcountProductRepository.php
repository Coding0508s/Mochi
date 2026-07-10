<?php

namespace App\Repositories\Store;

use App\Models\StoreReturnEcountProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StoreReturnEcountProductRepository
{
    /**
     * @return array<int, string>
     */
    public function getActiveProductCodes(): array
    {
        return StoreReturnEcountProduct::query()
            ->active()
            ->orderBy('prod_cd')
            ->orderBy('id')
            ->pluck('prod_cd')
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->filter(fn (string $code): bool => $code !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $productCodes
     * @return array<string, string> normalized_prod_cd => product_name
     */
    public function getProductNameMapByCodes(array $productCodes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            $productCodes,
        ), static fn (string $code): bool => $code !== '')));

        if ($codes === []) {
            return [];
        }

        return StoreReturnEcountProduct::query()
            ->whereIn('prod_cd', $codes)
            ->get(['prod_cd', 'product_name'])
            ->reduce(function (array $carry, StoreReturnEcountProduct $product): array {
                $code = strtoupper(trim((string) $product->prod_cd));
                $name = trim((string) ($product->product_name ?? ''));
                if ($code !== '' && $name !== '') {
                    $carry[$code] = $name;
                }

                return $carry;
            }, []);
    }

    public function paginate(string $search, int $perPage = 20): LengthAwarePaginator
    {
        return StoreReturnEcountProduct::query()
            ->when(trim($search) !== '', function ($query) use ($search): void {
                $keyword = strtoupper(trim($search));
                $query->where(function ($inner) use ($keyword): void {
                    $inner->whereRaw('UPPER(prod_cd) like ?', ["%{$keyword}%"])
                        ->orWhereRaw('UPPER(product_name) like ?', ["%{$keyword}%"]);
                });
            })
            ->orderBy('prod_cd')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * 이카운트에서 조회한 품목명을 DB에 저장합니다.
     * product_name 이 비어 있는 행만 갱신해, 이미 저장된 이름은 덮어쓰지 않습니다.
     *
     * @param  array<string, string>  $productNamesByCode  normalized_prod_cd => product_name
     */
    public function backfillMissingProductNames(array $productNamesByCode): int
    {
        $updated = 0;

        foreach ($productNamesByCode as $code => $name) {
            $normalizedCode = strtoupper(trim((string) $code));
            $trimmedName = trim((string) $name);
            if ($normalizedCode === '' || $trimmedName === '') {
                continue;
            }

            $affected = StoreReturnEcountProduct::query()
                ->where('prod_cd', $normalizedCode)
                ->where(function ($query): void {
                    $query->whereNull('product_name')
                        ->orWhere('product_name', '');
                })
                ->update(['product_name' => mb_substr($trimmedName, 0, 255)]);

            $updated += (int) $affected;
        }

        return $updated;
    }

    public function create(
        string $prodCd,
        bool $isActive = true,
        int $sortOrder = 0,
        ?string $memo = null,
        ?string $productName = null,
    ): StoreReturnEcountProduct {
        return StoreReturnEcountProduct::query()->create([
            'prod_cd' => strtoupper(trim($prodCd)),
            'product_name' => $productName !== null && trim($productName) !== '' ? trim($productName) : null,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
            'memo' => $memo !== null ? trim($memo) : null,
        ]);
    }

    public function update(StoreReturnEcountProduct $product, array $attributes): StoreReturnEcountProduct
    {
        if (array_key_exists('prod_cd', $attributes)) {
            $attributes['prod_cd'] = strtoupper(trim((string) $attributes['prod_cd']));
        }

        if (array_key_exists('product_name', $attributes) && $attributes['product_name'] !== null) {
            $attributes['product_name'] = trim((string) $attributes['product_name']);
            if ($attributes['product_name'] === '') {
                $attributes['product_name'] = null;
            }
        }

        if (array_key_exists('memo', $attributes) && $attributes['memo'] !== null) {
            $attributes['memo'] = trim((string) $attributes['memo']);
        }

        $product->fill($attributes);
        $product->save();

        return $product->refresh();
    }

    public function delete(StoreReturnEcountProduct $product): void
    {
        $product->delete();
    }
}
