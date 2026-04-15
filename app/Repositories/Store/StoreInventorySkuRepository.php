<?php

namespace App\Repositories\Store;

use App\Models\StoreInventorySku;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StoreInventorySkuRepository
{
    /**
     * @return array<int, string>
     */
    public function getActiveProductCodes(): array
    {
        return StoreInventorySku::query()
            ->active()
            ->orderBy('sort_order')
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
            ->orderBy('sort_order')
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
}
