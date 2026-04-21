<?php

namespace App\Repositories\Store;

use App\Models\StoreInventorySku;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StoreInventorySkuRepository
{
    /**
     * @param  array<int, string>  $productCodes
     * @return array<string, string> normalized_prod_cd => image_path
     */
    public function getImagePathMapByProductCodes(array $productCodes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            $productCodes
        ), static fn (string $code): bool => $code !== '')));

        if ($codes === []) {
            return [];
        }

        return StoreInventorySku::query()
            ->whereIn('prod_cd', $codes)
            ->get(['prod_cd', 'image_url'])
            ->reduce(function (array $carry, StoreInventorySku $sku): array {
                $path = StoreInventorySku::normalizeImagePath((string) ($sku->image_url ?? ''));
                if ($path === '') {
                    return $carry;
                }

                $carry[strtoupper(trim((string) $sku->prod_cd))] = $path;

                return $carry;
            }, []);
    }

    /**
     * @return array<int, string>
     */
    public function getActiveProductCodes(): array
    {
        return StoreInventorySku::query()
            ->active()
            ->orderBy('prod_cd')
            ->orderBy('id')
            ->pluck('prod_cd')
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->filter(fn (string $code): bool => $code !== '')
            ->values()
            ->all();
    }

    public function paginate(string $search, int $perPage = 20): LengthAwarePaginator
    {
        return StoreInventorySku::query()
            ->when(trim($search) !== '', function ($query) use ($search): void {
                $keyword = strtoupper(trim($search));
                $query->whereRaw('UPPER(prod_cd) like ?', ["%{$keyword}%"]);
            })
            ->orderBy('prod_cd')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function create(string $prodCd, bool $isActive = true, int $sortOrder = 0, ?string $memo = null): StoreInventorySku
    {
        return StoreInventorySku::query()->create([
            'prod_cd' => strtoupper(trim($prodCd)),
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
            'memo' => $memo !== null ? trim($memo) : null,
        ]);
    }

    public function update(StoreInventorySku $sku, array $attributes): StoreInventorySku
    {
        if (array_key_exists('prod_cd', $attributes)) {
            $attributes['prod_cd'] = strtoupper(trim((string) $attributes['prod_cd']));
        }

        if (array_key_exists('memo', $attributes) && $attributes['memo'] !== null) {
            $attributes['memo'] = trim((string) $attributes['memo']);
        }

        $sku->fill($attributes);
        $sku->save();

        return $sku->refresh();
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, StoreInventorySku>
     */
    public function findMany(array $ids): Collection
    {
        return StoreInventorySku::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * 플랫폼 DB의 연동 행만 삭제합니다. 이카운트 ERP는 호출하지 않습니다.
     */
    public function delete(StoreInventorySku $sku): void
    {
        $sku->delete();
    }
}
