<?php

namespace App\Support;

use App\Repositories\Store\StoreReturnEcountProductRepository;
use Throwable;

final class StoreReturnProductCodeResolver
{
    /**
     * 반품 등록 전용 이카운트 품목코드(PROD_CD) 목록.
     * DB 등록 품목을 우선하고, 없으면 환경설정 fallback을 사용합니다.
     * Store 재고 연동(store_inventory_skus)과 무관합니다.
     *
     * @return list<string>
     */
    public function resolveProductCodes(): array
    {
        try {
            $codesFromDb = app(StoreReturnEcountProductRepository::class)->getActiveProductCodes();
        } catch (Throwable $exception) {
            report($exception);
            $codesFromDb = [];
        }

        if ($codesFromDb !== []) {
            return array_values(array_unique($codesFromDb));
        }

        return $this->parseProdCdListFromConfig(
            (string) config('store.return_registration.ecount_product_codes', ''),
        );
    }

    /**
     * @return list<string>
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

        $parts = array_values(array_unique(array_map(
            static fn (string $part): string => strtoupper(trim($part)),
            $parts,
        )));
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        $max = max(1, (int) config('store.return_registration.ecount_product_max_codes', 100));

        if (count($parts) > $max) {
            $parts = array_slice($parts, 0, $max);
        }

        return $parts;
    }
}
