<?php

namespace App\Services\Store;

use App\Repositories\Store\StoreInventorySkuRepository;
use Throwable;

final class StoreProductCodeResolver
{
    /**
     * @return array<int, string>
     */
    public function resolveTargetProductCodes(): array
    {
        try {
            $codesFromDb = app(StoreInventorySkuRepository::class)->getActiveProductCodes();
        } catch (Throwable $exception) {
            report($exception);
            $codesFromDb = [];
        }

        if ($codesFromDb !== []) {
            return array_values(array_unique($codesFromDb));
        }

        return $this->parseProdCdListFromConfig((string) config('store.ecount.product_code', ''));
    }

    /**
     * @return array<int, string>
     */
    private function parseProdCdListFromConfig(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return [];
        }

        $parts = array_values(array_unique(array_map(static fn (string $part): string => trim($part), $parts)));
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        $max = (int) config('store.ecount.inventory_max_prod_cd', 20);
        if ($max < 1) {
            $max = 20;
        }

        if (count($parts) > $max) {
            $parts = array_slice($parts, 0, $max);
        }

        return $parts;
    }
}
