<?php

namespace App\Services\Store;

use App\Repositories\GrapeSeed\GnuboardSalesHistoryRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

final class StoreInventoryApiClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchInventory(): array
    {
        $dataSource = strtolower((string) config('store.data_source', 'ecount'));

        return match ($dataSource) {
            'ecount' => app(EcountApiClient::class)->fetchWarehouseInventory(),
            default => throw new RuntimeException("지원하지 않는 재고 데이터 소스입니다: {$dataSource}"),
        };
    }

    /**
     * @return array<int, array{product_code:string,product_name:string,histories:array<int, array<string, mixed>>}>
     */
    public function fetchSaleHistories(int $limitPerProduct = 5): array
    {
        $dataSource = strtolower((string) config('store.sales_history_source', config('store.data_source', 'ecount')));

        return match ($dataSource) {
            'ecount' => app(EcountApiClient::class)->fetchProductSaleHistories($limitPerProduct),
            'gnuboard' => $this->fetchGnuboardSaleHistories($limitPerProduct),
            default => throw new RuntimeException("지원하지 않는 재고 데이터 소스입니다: {$dataSource}"),
        };
    }

    /**
     * @return array<int, array{product_code:string,product_name:string,histories:array<int, array{qty:int,at:string,type:string,reason:string,ref:string}>}>
     */
    private function fetchGnuboardSaleHistories(int $limitPerProduct): array
    {
        $codes = app(StoreProductCodeResolver::class)->resolveTargetProductCodes();
        if ($codes === []) {
            return [];
        }

        return app(GnuboardSalesHistoryRepository::class)->getRecentSaleHistoriesByProductCodes($codes, $limitPerProduct);
    }

    /**
     * 단일 품목의 판매·출고 라인을 기간으로 조회합니다(판매내역 모달용).
     *
     * @return array<int, array{qty:int,at:string,type:string,reason:string,ref:string,order_customer_name:string}>
     */
    public function fetchSaleHistoriesForProductInDateRange(
        string $productCode,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        int $maxRows = 300,
    ): array {
        $dataSource = strtolower((string) config('store.sales_history_source', config('store.data_source', 'ecount')));

        return match ($dataSource) {
            'ecount' => app(EcountApiClient::class)->fetchProductSaleHistoriesForProductInDateRange(
                $productCode,
                $startDate,
                $endDate,
                $maxRows,
            ),
            'gnuboard' => app(GnuboardSalesHistoryRepository::class)->getSaleHistoriesForProductCodeBetween(
                $productCode,
                CarbonImmutable::parse($startDate)->startOfDay(),
                CarbonImmutable::parse($endDate)->endOfDay(),
                $maxRows,
            ),
            default => throw new RuntimeException("지원하지 않는 재고 데이터 소스입니다: {$dataSource}"),
        };
    }

    public function fetchAllPaginatedSaleHistories(
        ?string $search,
        ?CarbonInterface $startDate,
        ?CarbonInterface $endDate,
        int $perPage = 20
    ): LengthAwarePaginator {
        $dataSource = strtolower((string) config('store.sales_history_source', config('store.data_source', 'ecount')));

        return match ($dataSource) {
            'gnuboard' => app(GnuboardSalesHistoryRepository::class)
                ->getPaginatedAllSaleHistories($search, $startDate, $endDate, $perPage),
            default => throw new RuntimeException('전체 내역 조회는 gnuboard 소스만 지원합니다.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateInventoryItem(string $productCode, array $payload): array
    {
        throw new RuntimeException('Store 재고는 이카운트 창고재고 기준 조회 전용입니다.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $updates
     * @return array<int, array<string, mixed>>
     */
    public function bulkUpdateInventory(array $updates): array
    {
        throw new RuntimeException('Store 재고는 이카운트 창고재고 기준 조회 전용입니다.');
    }
}
