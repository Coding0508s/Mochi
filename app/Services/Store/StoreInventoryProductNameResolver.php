<?php

namespace App\Services\Store;

use App\Repositories\GrapeSeed\GnuboardShopItemRepository;
use App\Repositories\Store\StoreInventorySkuRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * 재고 화면 품목명. 쇼핑몰 이름, 저장된 이름, 이카운트 품목조회 순으로 채웁니다.
 * 품목조회는 실서버 한도(기본 10분에 1회)에 맞춰 한 번만 호출합니다.
 */
final class StoreInventoryProductNameResolver
{
    public function __construct(
        private readonly GnuboardShopItemRepository $gnuboardShopItemRepository,
        private readonly StoreInventorySkuRepository $storeInventorySkuRepository,
    ) {}

    /**
     * @param  array<int, string>  $productCodes
     * @return array<string, string> normalized_prod_cd => product_name
     */
    public function namesForCodes(array $productCodes): array
    {
        $codes = $this->normalizeCodes($productCodes);
        if ($codes === []) {
            return [];
        }

        $gnuboardNames = $this->gnuboardNames($codes);
        $storedNames = $this->storeInventorySkuRepository->getStoredProductNameMapByProductCodes($codes);

        $missing = [];
        foreach ($codes as $code) {
            if (trim((string) ($gnuboardNames[$code] ?? '')) !== '') {
                continue;
            }
            if (trim((string) ($storedNames[$code] ?? '')) !== '') {
                continue;
            }
            $missing[] = $code;
        }

        $ecountNames = $this->lookupMissingFromEcountOnce($missing);
        if ($ecountNames !== []) {
            $this->storeInventorySkuRepository->fillEmptyProductNames($ecountNames);
        }

        $resolved = [];
        foreach ($codes as $code) {
            $name = trim((string) ($gnuboardNames[$code] ?? ''));
            if ($name === '') {
                $name = trim((string) ($storedNames[$code] ?? ''));
            }
            if ($name === '') {
                $name = trim((string) ($ecountNames[$code] ?? ''));
            }
            if ($name !== '') {
                $resolved[$code] = $name;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, string>
     */
    private function gnuboardNames(array $codes): array
    {
        try {
            return $this->gnuboardShopItemRepository->getProductNameMapByProductCodes($codes);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * 이름이 없는 코드만, 간격 안에 한 번, 한 청크만 조회합니다.
     *
     * @param  array<int, string>  $missingCodes
     * @return array<string, string>
     */
    private function lookupMissingFromEcountOnce(array $missingCodes): array
    {
        if ($missingCodes === [] || ! $this->shouldQueryEcount()) {
            return [];
        }

        $interval = (int) config('store.ecount.product_name_lookup_interval_seconds', 600);
        if ($interval > 0 && ! Cache::add($this->lookupCacheKey(), true, now()->addSeconds($interval))) {
            return [];
        }

        $chunkSize = (int) config('store.ecount.product_basic_chunk_size', 20);
        if ($chunkSize < 1) {
            $chunkSize = 20;
        }

        $batch = array_slice($missingCodes, 0, $chunkSize);

        try {
            return app(EcountApiClient::class)->fetchProductDisplayNamesByCodes($batch);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function shouldQueryEcount(): bool
    {
        if (! (bool) config('store.ecount.fetch_product_names', true)) {
            return false;
        }

        return strtolower((string) config('store.data_source', 'ecount')) === 'ecount';
    }

    private function lookupCacheKey(): string
    {
        $prefix = (string) config('store.ecount.cache_prefix', 'store_inventory');

        return $prefix.':product_name_lookup_lock';
    }

    /**
     * @param  array<int, string>  $productCodes
     * @return array<int, string>
     */
    private function normalizeCodes(array $productCodes): array
    {
        return array_values(array_unique(array_filter(array_map(
            static function (string $code): string {
                $code = strtoupper(trim($code));

                return preg_replace('/^[\p{Zs}]+|[\p{Zs}]+$/u', '', $code) ?? $code;
            },
            $productCodes
        ), static fn (string $code): bool => $code !== '')));
    }
}
