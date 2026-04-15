<?php

namespace App\Services\Store;

use App\Repositories\GrapeSeed\GnuboardShopItemRepository;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class EcountApiClient
{
    public function __construct(
        private readonly GnuboardShopItemRepository $gnuboardShopItemRepository,
        private readonly StoreProductCodeResolver $storeProductCodeResolver,
    ) {}

    /**
     * @return array<int, array{product_code:string,product_name:string,histories:array<int, array<string, mixed>>}>
     */
    public function fetchProductSaleHistories(int $limitPerProduct = 5): array
    {
        $limit = max(1, $limitPerProduct);
        $sessionId = $this->resolveSessionId();
        if ($sessionId === '') {
            throw new InvalidArgumentException('ECOUNT_SESSION_ID 값이 비어 있습니다.');
        }

        $productCodes = $this->resolveTargetProductCodes();
        if ($productCodes === []) {
            return [];
        }

        $nameMap = [];
        if ((bool) config('store.ecount.fetch_product_names', true)) {
            $nameMap = $this->fetchBasicProductNames($productCodes);
        }

        $chunkSize = max(1, (int) config('store.ecount.movement_chunk_size', 20));
        $joiner = $this->ecountProdCdJoiner();

        /** @var array<string, array<int, array{qty:int,at:string,type:string,reason:string,ref:string,ts:int}>> $historiesByCode */
        $historiesByCode = [];

        $chunks = array_chunk($productCodes, $chunkSize);
        $chunkCount = count($chunks);
        $failedChunkCount = 0;
        $collectedRows = 0;

        foreach ($chunks as $chunk) {
            if ($chunk === []) {
                continue;
            }

            $joined = implode($joiner, $chunk);
            try {
                $rows = $this->fetchHistoryRowsForChunk($sessionId, $joined, null, null);
            } catch (Throwable $exception) {
                report($exception);
                $failedChunkCount++;

                continue;
            }

            $collectedRows += count($rows);

            foreach ($rows as $row) {
                $normalized = $this->normalizeDeductRow($row);
                if ($normalized === null) {
                    continue;
                }

                $code = $normalized['code'];
                if ($code === '') {
                    continue;
                }

                $historiesByCode[$code] ??= [];
                $historiesByCode[$code][] = [
                    'qty' => (int) $normalized['qty'],
                    'at' => (string) $normalized['at'],
                    'type' => (string) $normalized['type'],
                    'reason' => (string) $normalized['reason'],
                    'ref' => (string) $normalized['ref'],
                    'ts' => (int) $normalized['ts'],
                ];
            }
        }

        if ($chunkCount > 0 && $failedChunkCount >= $chunkCount && $collectedRows === 0) {
            throw new RuntimeException('판매내역 API 조회에 실패했습니다.');
        }

        $result = [];
        foreach ($productCodes as $code) {
            $items = $historiesByCode[$code] ?? [];
            usort($items, static fn (array $a, array $b): int => (int) ($b['ts'] ?? 0) <=> (int) ($a['ts'] ?? 0));
            $items = array_slice($items, 0, $limit);

            $result[] = [
                'product_code' => $code,
                'product_name' => (string) ($nameMap[$code] ?? $code),
                'histories' => array_map(static fn (array $item): array => [
                    'qty' => (int) ($item['qty'] ?? 0),
                    'at' => (string) ($item['at'] ?? ''),
                    'type' => (string) ($item['type'] ?? ''),
                    'reason' => (string) ($item['reason'] ?? ''),
                    'ref' => (string) ($item['ref'] ?? ''),
                    'order_customer_name' => '',
                ], $items),
            ];
        }

        return $result;
    }

    /**
     * 단일 품목의 판매·수불 이력을 지정 기간(이카운트 START_DATE/END_DATE)으로 조회한다.
     *
     * @return array<int, array{qty:int,at:string,type:string,reason:string,ref:string,order_customer_name:string}>
     */
    public function fetchProductSaleHistoriesForProductInDateRange(
        string $productCode,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        int $maxRows = 300,
    ): array {
        $code = strtoupper(trim($productCode));
        if ($code === '') {
            return [];
        }

        $sessionId = $this->resolveSessionId();
        if ($sessionId === '') {
            throw new InvalidArgumentException('ECOUNT_SESSION_ID 값이 비어 있습니다.');
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        if ($end->lessThan($start)) {
            return [];
        }

        $joined = $code;

        $rows = $this->fetchHistoryRowsForChunk($sessionId, $joined, $start, $end);

        $items = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeDeductRow($row);
            if ($normalized === null) {
                continue;
            }

            if (strtoupper($normalized['code']) !== $code) {
                continue;
            }

            $items[] = [
                'qty' => (int) $normalized['qty'],
                'at' => (string) $normalized['at'],
                'type' => (string) $normalized['type'],
                'reason' => (string) $normalized['reason'],
                'ref' => (string) $normalized['ref'],
                'ts' => (int) $normalized['ts'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => (int) ($b['ts'] ?? 0) <=> (int) ($a['ts'] ?? 0));
        $items = array_slice($items, 0, max(1, $maxRows));

        return array_map(static fn (array $item): array => [
            'qty' => (int) ($item['qty'] ?? 0),
            'at' => (string) ($item['at'] ?? ''),
            'type' => (string) ($item['type'] ?? ''),
            'reason' => (string) ($item['reason'] ?? ''),
            'ref' => (string) ($item['ref'] ?? ''),
            'order_customer_name' => '',
        ], $items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchWarehouseInventory(): array
    {
        $warehouseRows = $this->fetchWarehouseRows();

        $productCodes = [];
        foreach ($warehouseRows as $row) {
            $code = (string) $this->pick($row, ['PROD_CD', 'prod_cd', 'product_code', 'productCode', 'item_code'], '');
            if ($code !== '' && $code !== '-') {
                $productCodes[] = $code;
            }
        }
        $productCodes = array_values(array_unique($productCodes));

        $nameMap = [];
        if ((bool) config('store.ecount.fetch_product_names', true) && $productCodes !== []) {
            $nameMap = $this->fetchBasicProductNames($productCodes);
        }

        $notifyQtyMap = [];
        if ($productCodes !== []) {
            $notifyQtyMap = $this->gnuboardShopItemRepository->getNotifyQuantityMapByProductCodes($productCodes);
        }

        $actualStockQtyMap = [];
        if ($productCodes !== []) {
            $actualStockQtyMap = $this->gnuboardShopItemRepository->getStockQuantityMapByProductCodes($productCodes);
        }

        $deductMap = [];
        if ($productCodes !== []) {
            $deductMap = $this->fetchRecentDeductLogs($productCodes);
        }

        return array_map(function (array $row) use ($nameMap, $notifyQtyMap, $actualStockQtyMap, $deductMap): array {
            $productCode = (string) $this->pick($row, ['PROD_CD', 'prod_cd', 'product_code', 'productCode', 'item_code'], '');
            $warehouseStock = $this->toInt($this->pick($row, ['BAL_QTY', 'bal_qty', 'warehouse_stock', 'stock_qty'], 0));
            $pendingOrder = 0;
            $availableStock = max(0, $warehouseStock - $pendingOrder);
            $notifyQty = $productCode !== ''
                ? (int) ($notifyQtyMap[strtoupper($productCode)] ?? 0)
                : 0;
            $actualStockQty = $productCode !== ''
                ? (int) ($actualStockQtyMap[strtoupper($productCode)] ?? 0)
                : 0;

            $fromRowName = (string) $this->pick($row, ['PROD_DES', 'prod_des', 'product_name', 'item_name'], '');
            $fromListName = ($productCode !== '' && $productCode !== '-') ? ($nameMap[$productCode] ?? null) : null;
            $productName = $fromListName !== null && $fromListName !== ''
                ? $fromListName
                : ($fromRowName !== '' ? $fromRowName : '-');
            $deduct = $deductMap[$productCode] ?? null;

            return [
                'product_code' => $productCode !== '' ? $productCode : '-',
                'product_name' => $productName,
                'image_url' => '',
                'warehouse_stock' => $warehouseStock,
                'actual_stock_quantity' => max(0, $actualStockQty),
                'pending_order' => $pendingOrder,
                'available_stock' => $availableStock,
                'notify_quantity' => max(0, $notifyQty),
                'is_sellable' => $availableStock > 0,
                'is_sold_out' => $availableStock <= 0,
                'exclude_member_discount' => false,
                'last_deduct_qty' => is_array($deduct) ? (int) ($deduct['qty'] ?? 0) : null,
                'last_deduct_at' => is_array($deduct) ? (string) ($deduct['at'] ?? '') : '',
                'last_deduct_type' => is_array($deduct) ? (string) ($deduct['type'] ?? '') : '',
                'last_deduct_reason' => is_array($deduct) ? (string) ($deduct['reason'] ?? '') : '',
                'last_deduct_ref' => is_array($deduct) ? (string) ($deduct['ref'] ?? '') : '',
            ];
        }, $warehouseRows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchWarehouseRows(): array
    {
        $endpoint = (string) config('store.ecount.inventory_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus');
        $sessionId = $this->resolveSessionId();
        if ($sessionId === '') {
            throw new InvalidArgumentException('ECOUNT_SESSION_ID 값이 비어 있습니다.');
        }
        $targetProductCodes = $this->resolveTargetProductCodes();
        $max = (int) config('store.ecount.inventory_max_prod_cd', 20);
        if ($max < 1) {
            $max = 20;
        }

        // 지정 품목이 없으면 이카운트 전체 조회(기존 동작 유지)
        if ($targetProductCodes === []) {
            $payload = $this->postEcountJson($endpoint, $sessionId, $this->buildInventoryBody(''));

            return $this->extractRows($payload);
        }

        $mergedByCode = [];
        foreach (array_chunk($targetProductCodes, $max) as $chunk) {
            $payload = $this->postEcountJson(
                $endpoint,
                $sessionId,
                $this->buildInventoryBody(implode($this->ecountProdCdJoiner(), $chunk))
            );

            foreach ($this->extractRows($payload) as $row) {
                $code = (string) ($row['PROD_CD'] ?? $row['prod_cd'] ?? '');
                if ($code === '') {
                    continue;
                }

                // 동일 코드가 여러 청크에서 다시 오면 마지막 값을 우선
                $mergedByCode[$code] = $row;
            }
        }

        return array_values($mergedByCode);
    }

    /**
     * @return array<int, string>
     */
    private function resolveTargetProductCodes(): array
    {
        return $this->storeProductCodeResolver->resolveTargetProductCodes();
    }

    /**
     * 품목 기본 조회로 품목명(PROD_DES)을 가져옵니다. 다건 시 품목코드는 이카운트 규격 구분자로 연결합니다.
     *
     * @param  array<int, string>  $productCodes
     * @return array<string, string> product_code => product_name
     */
    private function fetchBasicProductNames(array $productCodes): array
    {
        $endpoint = (string) config('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        $sessionId = $this->resolveSessionId();
        if ($sessionId === '') {
            return [];
        }

        $chunkSize = (int) config('store.ecount.product_basic_chunk_size', 20);
        if ($chunkSize < 1) {
            $chunkSize = 20;
        }

        $joiner = $this->ecountProdCdJoiner();

        $map = [];
        foreach (array_chunk($productCodes, $chunkSize) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            try {
                $payload = $this->postEcountJson($endpoint, $sessionId, [
                    'PROD_CD' => implode($joiner, $chunk),
                ]);

                foreach ($this->extractRows($payload) as $row) {
                    $code = (string) ($row['PROD_CD'] ?? '');
                    $name = (string) ($row['PROD_DES'] ?? '');
                    if ($code !== '') {
                        $map[$code] = $name;
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $productCodes
     * @return array<string, array{qty:int, at:string, type:string, reason:string, ref:string, ts:int}>
     */
    private function fetchRecentDeductLogs(array $productCodes): array
    {
        if (! (bool) config('store.ecount.fetch_deduct_logs', true)) {
            return [];
        }

        $movementEndpoint = $this->sanitizeEndpoint((string) config('store.ecount.movement_endpoint', ''));
        $saleEndpoint = $this->sanitizeEndpoint((string) config('store.ecount.sale_list_endpoint', ''));

        if ($movementEndpoint === '' && $saleEndpoint === '') {
            return [];
        }

        $sessionId = $this->resolveSessionId();
        if ($sessionId === '') {
            return [];
        }

        $chunkSize = max(1, (int) config('store.ecount.movement_chunk_size', 20));
        $joiner = $this->ecountProdCdJoiner();

        $latestByCode = [];
        foreach (array_chunk($productCodes, $chunkSize) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            $joined = implode($joiner, $chunk);

            if ($movementEndpoint !== '') {
                try {
                    $body = $this->buildMovementBody($joined, null, null);
                    $payload = $this->postEcountJson($movementEndpoint, $sessionId, $body);
                    $latestByCode = $this->mergeDeductRowsIntoMap($latestByCode, $this->extractRows($payload));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            if ($saleEndpoint !== '') {
                try {
                    $body = $this->buildSaleListBody($joined, null, null);
                    $payload = $this->postEcountJson($saleEndpoint, $sessionId, $body);
                    $latestByCode = $this->mergeDeductRowsIntoMap($latestByCode, $this->extractRows($payload));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $latestByCode;
    }

    /**
     * 판매내역: GetListSale은 수불과 요청 본문이 다를 수 있어 별도 본문을 사용합니다.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchHistoryRowsForChunk(string $sessionId, string $prodCdJoined, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $saleEndpoint = $this->sanitizeEndpoint((string) config('store.ecount.sale_list_endpoint', ''));
        $movementEndpoint = $this->sanitizeEndpoint((string) config('store.ecount.movement_endpoint', ''));

        $saleException = null;
        $saleRows = [];

        if ($saleEndpoint !== '') {
            try {
                $saleBody = $this->buildSaleListBody($prodCdJoined, $from, $to);
                $payload = $this->postEcountJson($saleEndpoint, $sessionId, $saleBody);
                $saleRows = $this->extractRows($payload);
                if ($saleRows !== []) {
                    return $saleRows;
                }
            } catch (Throwable $exception) {
                report($exception);
                $saleException = $exception;
            }
        }

        // 요구사항: Sale 조회 실패 또는 빈 응답이면 movement fallback 사용
        if ($movementEndpoint !== '') {
            try {
                $movementBody = $this->buildMovementBody($prodCdJoined, $from, $to);
                $payload = $this->postEcountJson($movementEndpoint, $sessionId, $movementBody);

                return $this->extractRows($payload);
            } catch (Throwable $exception) {
                report($exception);

                throw new RuntimeException('판매내역 API 조회에 실패했습니다.', previous: $exception);
            }
        }

        if ($saleException !== null) {
            throw new RuntimeException('판매내역 API 조회에 실패했습니다.', previous: $saleException);
        }

        return $saleRows;
    }

    private function sanitizeEndpoint(string $endpoint): string
    {
        $normalized = trim($endpoint);
        if ($normalized === '') {
            return '';
        }

        return rtrim($normalized, '?&');
    }

    /**
     * @param  array<string, array{qty:int, at:string, type:string, reason:string, ref:string, ts:int}>  $latestByCode
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{qty:int, at:string, type:string, reason:string, ref:string, ts:int}>
     */
    private function mergeDeductRowsIntoMap(array $latestByCode, array $rows): array
    {
        foreach ($rows as $row) {
            $normalized = $this->normalizeDeductRow($row);
            if ($normalized === null) {
                continue;
            }

            $code = $normalized['code'];
            if ($code === '') {
                continue;
            }

            $current = $latestByCode[$code] ?? null;
            if (! is_array($current) || (int) $normalized['ts'] >= (int) ($current['ts'] ?? 0)) {
                $latestByCode[$code] = [
                    'qty' => (int) $normalized['qty'],
                    'at' => (string) $normalized['at'],
                    'type' => (string) $normalized['type'],
                    'reason' => (string) $normalized['reason'],
                    'ref' => (string) $normalized['ref'],
                    'ts' => (int) $normalized['ts'],
                ];
            }
        }

        return $latestByCode;
    }

    /**
     * 수불 이력(GetListInventoryMovementHistory 등) 조회용 본문.
     *
     * @return array<string, string>
     */
    private function buildMovementBody(string $prodCd, ?Carbon $from = null, ?Carbon $to = null): array
    {
        [$fromDate, $toDate] = $this->resolveMovementDateRange($from, $to);

        return [
            'PROD_CD' => $prodCd,
            'WH_CD' => (string) config('store.ecount.warehouse_code', ''),
            'START_DATE' => $fromDate,
            'END_DATE' => $toDate,
        ];
    }

    /**
     * 판매 목록 조회(GetListSale)용 본문. 수불과 필수 필드가 다를 수 있어 분리합니다.
     * BASE_DATE·COM_CODE는 매뉴얼/운영 환경에 맞게 조정하세요.
     *
     * @return array<string, string>
     */
    private function buildSaleListBody(string $prodCd, ?Carbon $from = null, ?Carbon $to = null): array
    {
        [$fromDate, $toDate] = $this->resolveMovementDateRange($from, $to);

        $body = [
            'PROD_CD' => $prodCd,
            'WH_CD' => (string) config('store.ecount.warehouse_code', ''),
            'START_DATE' => $fromDate,
            'END_DATE' => $toDate,
            'BASE_DATE' => $toDate,
        ];

        $comCode = trim((string) config('store.ecount.com_code', ''));
        if ($comCode !== '' && (bool) config('store.ecount.sale_list_include_com_code', true)) {
            $body['COM_CODE'] = $comCode;
        }

        return $body;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveMovementDateRange(?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from !== null && $to !== null) {
            return [$from->copy()->format('Ymd'), $to->copy()->format('Ymd')];
        }

        $toDate = Carbon::now();
        $lookbackDays = max(1, (int) config('store.ecount.movement_lookback_days', 30));
        $fromDate = $toDate->copy()->subDays($lookbackDays);

        return [$fromDate->format('Ymd'), $toDate->format('Ymd')];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{code:string,qty:int,at:string,type:string,reason:string,ref:string,ts:int}|null
     */
    private function normalizeDeductRow(array $row): ?array
    {
        $productCode = trim((string) $this->pick($row, ['PROD_CD', 'prod_cd', 'ITEM_CD', 'item_cd', 'PRODUCT_CODE'], ''));
        if ($productCode === '') {
            return null;
        }

        $deductQty = $this->extractDeductQty($row);
        if ($deductQty <= 0) {
            return null;
        }

        $occurredAtRaw = $this->resolveDeductOccurredAtRaw($row);
        $timestamp = $this->toTimestamp($occurredAtRaw);
        $occurredAtDisplay = $timestamp > 0 ? Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s') : $occurredAtRaw;

        $reason = trim((string) $this->pick($row, ['REMARKS', 'REMARK', 'MEMO', 'BIGO', 'DESC'], ''));
        $reference = trim((string) $this->pick($row, [
            'SLIP_NO', 'DOC_NO', 'NO', 'SEQ_NO', 'ORDER_NO',
            'SALE_NO', 'sale_no', 'IO_NO', 'io_no', 'HISTORY_NO',
        ], ''));

        return [
            'code' => $productCode,
            'qty' => $deductQty,
            'at' => $occurredAtDisplay,
            'type' => $this->resolveDeductTypeLabel($row),
            'reason' => $reason,
            'ref' => $reference,
            'ts' => $timestamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractDeductQty(array $row): int
    {
        $outQty = $this->toInt($this->pick($row, ['OUT_QTY', 'out_qty', 'OUTPUT_QTY'], 0));
        if ($outQty > 0) {
            return $outQty;
        }

        foreach (['QTY', 'qty', 'IO_QTY', 'INOUT_QTY', 'CHANGE_QTY', 'BAL_QTY'] as $key) {
            $value = $this->toInt($row[$key] ?? null);
            if ($value < 0) {
                return abs($value);
            }
        }

        $saleQty = $this->toInt($this->pick($row, [
            'SALE_QTY', 'sale_qty', 'SALE_QT', 'sale_qt',
            'PROD_QTY', 'prod_qty', 'TOT_QTY', 'tot_qty', 'QTY1', 'qty1',
        ], 0));
        if ($saleQty > 0) {
            return $saleQty;
        }

        $plainQty = $this->toInt($this->pick($row, ['QTY', 'qty'], 0));
        if ($plainQty > 0) {
            return $plainQty;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveDeductTypeLabel(array $row): string
    {
        if ($this->isPrintedSale($row)) {
            return '주문연동';
        }

        if (trim((string) $this->pick($row, ['SALE_NO', 'sale_no'], '')) !== '') {
            return '판매입력';
        }

        $typeRaw = mb_strtolower(trim((string) $this->pick($row, [
            'IO_GUBUN_NAME',
            'IO_GUBUN',
            'INOUT_TYPE',
            'TR_TYPE',
            'DOC_TYPE',
            'TYPE',
        ], '')));

        if ($typeRaw === '') {
            return '기타차감';
        }

        if (str_contains($typeRaw, 'order') || str_contains($typeRaw, '주문') || str_contains($typeRaw, '판매')) {
            return '주문연동';
        }

        if (str_contains($typeRaw, '자가') || str_contains($typeRaw, 'self')) {
            return '자가사용';
        }

        if (str_contains($typeRaw, '조정') || str_contains($typeRaw, 'adjust')) {
            return '재고조정';
        }

        return '기타차감';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isPrintedSale(array $row): bool
    {
        $status = mb_strtolower(trim((string) $this->pick($row, [
            'PRINT_YN',
            'PRINT_FLAG',
            'PRINT_STATUS',
            'PRINT',
            'STATUS_NM',
            'STATUS',
            'STATE',
        ], '')));

        if ($status === '') {
            return false;
        }

        return in_array($status, ['y', '1', 'printed', 'print'], true)
            || str_contains($status, '인쇄');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveDeductOccurredAtRaw(array $row): string
    {
        $pairs = [
            [['PRINT_DATETIME', 'PRINT_DT'], ['PRINT_TIME']],
            [['MOD_DATETIME', 'MOD_DT', 'UPDATE_DT', 'UPD_DT'], ['MOD_TIME', 'UPDATE_TIME']],
            [['SLIP_DATETIME', 'SLIP_DT', 'SLIP_DATE'], ['SLIP_TIME']],
            [['IO_DATE', 'INOUT_DATE', 'BASE_DATE', 'REG_DATE', 'DATE', 'date'], ['IO_TIME', 'TIME', 'time']],
        ];

        foreach ($pairs as [$dateKeys, $timeKeys]) {
            $date = trim((string) $this->pick($row, $dateKeys, ''));
            $time = trim((string) $this->pick($row, $timeKeys, ''));
            $raw = trim($date.' '.$time);
            if ($raw !== '') {
                return $raw;
            }
        }

        return '';
    }

    private function toTimestamp(string $raw): int
    {
        $value = trim($raw);
        if ($value === '') {
            return 0;
        }

        $normalized = str_replace('.', '-', $value);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        try {
            return Carbon::parse($normalized)->timestamp;
        } catch (Throwable) {
            return 0;
        }
    }

    private function resolveSessionId(): string
    {
        $configured = trim((string) config('store.ecount.session_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $cached = trim((string) Cache::get($this->cachePrefix().':session_id', ''));
        if ($cached !== '') {
            return $cached;
        }

        if (! (bool) config('store.ecount.auto_login_when_empty_session', true)) {
            return '';
        }

        $comCode = trim((string) config('store.ecount.com_code', ''));
        $userId = trim((string) config('store.ecount.user_id', ''));
        $apiCertKey = trim((string) config('store.ecount.api_cert_key', ''));
        if ($comCode === '' || $userId === '' || $apiCertKey === '') {
            return '';
        }

        try {
            return $this->refreshSessionId();
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }
    }

    /**
     * 이카운트 다건 품목코드 구분자(오픈 API에서 PROD_CD 연결에 사용, U+222C).
     */
    private function ecountProdCdJoiner(): string
    {
        return "\u{222C}";
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInventoryBody(string $prodCd): array
    {
        $baseDate = trim((string) config('store.ecount.base_date', ''));
        if ($baseDate === '') {
            $baseDate = Carbon::now()->format('Ymd');
        }

        return [
            'PROD_CD' => $prodCd,
            'WH_CD' => (string) config('store.ecount.warehouse_code', ''),
            'BASE_DATE' => $baseDate,
            'ZERO_FLAG' => (string) config('store.ecount.zero_flag', 'N'),
            'BAL_FLAG' => (string) config('store.ecount.bal_flag', 'N'),
            'DEL_GUBUN' => (string) config('store.ecount.del_gubun', 'N'),
            'SAFE_FLAG' => (string) config('store.ecount.safe_flag', 'N'),
        ];
    }

    /**
     * 이카운트 Open API 공통 응답(Status / Error) 판별. HTTP 200이어도 본문 Status가 실패일 수 있음.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    private function parseEcountApiStatus(array $payload): array
    {
        $statusRaw = $payload['Status'] ?? null;
        $statusOk = $statusRaw === null
            || $statusRaw === ''
            || $statusRaw === 200
            || $statusRaw === '200';
        if ($statusOk) {
            return ['ok' => true, 'message' => ''];
        }

        $message = is_array($payload['Error'] ?? null)
            ? (string) (($payload['Error']['Message'] ?? '') ?: ($payload['Error']['MessageDetail'] ?? ''))
            : '';

        return [
            'ok' => false,
            'message' => $message !== '' ? $message : 'Ecount API 응답이 실패 상태입니다.',
        ];
    }

    /**
     * OAPILogin 응답에서 SESSION_ID 추출. 이카운트 버전/호스트에 따라 Data 직하위 또는 Result[] 안에 올 수 있음.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractSessionIdFromOapiLoginPayload(array $payload): string
    {
        $pickFromRow = static function (mixed $row): string {
            if (! is_array($row)) {
                return '';
            }
            foreach (['SESSION_ID', 'SessionId', 'session_id'] as $key) {
                $v = trim((string) ($row[$key] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }

            return '';
        };

        foreach (['SESSION_ID', 'SessionId', 'session_id'] as $key) {
            $v = trim((string) ($payload[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        // 이카운트 OAPILogin은 Data(단수) 또는 Datas(복수) 키로 응답함
        $data = $payload['Data'] ?? $payload['data'] ?? $payload['Datas'] ?? $payload['datas'] ?? null;
        if (! is_array($data)) {
            return '';
        }

        // Datas 가 배열 목록(list)이면 첫 번째 원소에서 SESSION_ID 탐색
        if (array_is_list($data)) {
            foreach ($data as $row) {
                $v = $pickFromRow($row);
                if ($v !== '') {
                    return $v;
                }
            }

            return '';
        }

        $direct = $pickFromRow($data);
        if ($direct !== '') {
            return $direct;
        }

        // Data.Result 탐색
        $result = $data['Result'] ?? $data['result'] ?? null;
        if (is_array($result)) {
            if (array_is_list($result)) {
                foreach ($result as $row) {
                    $v = $pickFromRow($row);
                    if ($v !== '') {
                        return $v;
                    }
                }
            } else {
                return $pickFromRow($result);
            }
        }

        // Data.Datas 탐색 (이카운트가 Data 안에 Datas 배열을 포함하는 경우)
        $nestedDatas = $data['Datas'] ?? $data['datas'] ?? null;
        if (is_array($nestedDatas)) {
            if (array_is_list($nestedDatas)) {
                foreach ($nestedDatas as $row) {
                    $v = $pickFromRow($row);
                    if ($v !== '') {
                        return $v;
                    }
                }
            } else {
                $v = $pickFromRow($nestedDatas);
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postEcountJson(string $endpoint, string $sessionId, array $body): array
    {
        return $this->postEcountJsonInternal($endpoint, $sessionId, $body, true);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postEcountJsonInternal(string $endpoint, string $sessionId, array $body, bool $allowSessionRefresh): array
    {
        $baseUrl = $this->resolveBaseUrl();
        $timeout = (int) config('store.timeout', 10);
        if ($baseUrl === '') {
            throw new InvalidArgumentException('ECOUNT_API_BASE_URL 값이 비어 있습니다.');
        }

        $cacheTtl = $this->cacheTtlSeconds();
        $cacheKey = $this->cachePrefix().':'.sha1(json_encode([$baseUrl, $endpoint, $sessionId, $body], JSON_UNESCAPED_UNICODE));
        if ($cacheTtl > 0 && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $request = Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->acceptJson()
                ->contentType('application/json');
            $response = $request->post($endpoint.'?SESSION_ID='.rawurlencode($sessionId), $body);

            $payload = $response->throw()->json();
            if (! is_array($payload)) {
                throw new RuntimeException('Ecount API 응답 형식이 올바르지 않습니다.');
            }

            $parsed = $this->parseEcountApiStatus($payload);
            if (! $parsed['ok']) {
                $message = $parsed['message'];

                if ($allowSessionRefresh && $this->shouldRefreshSession($message)) {
                    $newSessionId = $this->refreshSessionId();
                    if ($newSessionId !== '' && $newSessionId !== $sessionId) {
                        return $this->postEcountJsonInternal($endpoint, $newSessionId, $body, false);
                    }
                }

                throw new RuntimeException(
                    $message === 'Ecount API 응답이 실패 상태입니다.'
                        ? $message
                        : 'Ecount API 오류: '.$message
                );
            }

            if ($cacheTtl > 0) {
                Cache::put($cacheKey, $payload, now()->addSeconds($cacheTtl));
            }

            return $payload;
        } catch (RequestException $exception) {
            throw new RuntimeException('Ecount API 호출에 실패했습니다.', previous: $exception);
        }
    }

    private function shouldRefreshSession(string $message): bool
    {
        if (! (bool) config('store.ecount.session_refresh_enabled', false)) {
            return false;
        }

        $normalized = mb_strtolower($message);

        return str_contains($normalized, 'session')
            || str_contains($normalized, '로그인')
            || str_contains($normalized, '만료');
    }

    private function refreshSessionId(): string
    {
        $comCode = trim((string) config('store.ecount.com_code', ''));
        $userId = trim((string) config('store.ecount.user_id', ''));
        $apiCertKey = trim((string) config('store.ecount.api_cert_key', ''));
        if ($comCode === '' || $userId === '' || $apiCertKey === '') {
            return '';
        }

        $zone = trim((string) config('store.ecount.zone', ''));
        if ($zone === '') {
            $zone = $this->fetchZone($comCode);
            if ($zone === '') {
                return '';
            }
        }

        $endpoint = (string) config('store.ecount.login_endpoint', '/OAPI/V2/OAPILogin');
        $baseUrl = $this->resolveBaseUrl();
        $timeout = (int) config('store.timeout', 10);
        if ($baseUrl === '') {
            return '';
        }

        $response = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->acceptJson()
            ->contentType('application/json')
            ->post($endpoint, [
                'COM_CODE' => $comCode,
                'USER_ID' => $userId,
                'API_CERT_KEY' => $apiCertKey,
                'LAN_TYPE' => (string) config('store.ecount.lan_type', 'ko-KR'),
                'ZONE' => $zone,
            ]);

        $payload = $response->throw()->json();
        if (! is_array($payload)) {
            return '';
        }

        // [DEBUG] OAPILogin 전체 응답 구조 로깅 — SESSION_ID 파싱 성공 후 제거 예정
        \Log::debug('[EcountApiClient] OAPILogin raw payload', ['payload' => $payload]);

        $parsed = $this->parseEcountApiStatus($payload);
        if (! $parsed['ok']) {
            report(new RuntimeException('Ecount OAPILogin 실패: '.$parsed['message']));

            return '';
        }

        $sessionId = $this->extractSessionIdFromOapiLoginPayload($payload);
        if ($sessionId === '') {
            $data = $payload['Data'] ?? $payload['data'] ?? null;
            $dataCode = is_array($data) ? ($data['Code'] ?? $data['code'] ?? '') : '';
            $dataMsg = is_array($data) ? ($data['Message'] ?? $data['message'] ?? '') : '';
            $loginType = is_array($data) ? ($data['LoginType'] ?? '') : '';
            report(new RuntimeException(
                'Ecount OAPILogin: SESSION_ID를 응답에서 찾지 못했습니다. '.
                "Data.Code={$dataCode}, LoginType={$loginType}, Data.Message={$dataMsg}. ".
                '이카운트 포털에서 OAPI 전용 인증키를 발급받아 ECOUNT_API_CERT_KEY에 설정하세요.'
            ));
        } else {
            \Log::debug('[EcountApiClient] OAPILogin SESSION_ID 추출 성공', ['session_id_prefix' => substr($sessionId, 0, 8).'...']);
            Cache::put($this->cachePrefix().':session_id', $sessionId, now()->addMinutes(30));
        }

        return $sessionId;
    }

    private function fetchZone(string $comCode): string
    {
        $endpoint = (string) config('store.ecount.zone_endpoint', '/OAPI/V2/Zone');
        $baseUrl = $this->resolveBaseUrl();
        $timeout = (int) config('store.timeout', 10);
        if ($baseUrl === '') {
            return '';
        }

        $response = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->acceptJson()
            ->contentType('application/json')
            ->post($endpoint, ['COM_CODE' => $comCode]);

        $payload = $response->throw()->json();
        if (! is_array($payload)) {
            return '';
        }

        $parsed = $this->parseEcountApiStatus($payload);
        if (! $parsed['ok']) {
            report(new RuntimeException('Ecount Zone 조회 실패: '.$parsed['message']));

            return '';
        }

        return trim((string) ($payload['Data']['ZONE'] ?? ''));
    }

    private function cacheTtlSeconds(): int
    {
        return max(0, (int) config('store.ecount.cache_ttl_seconds', 0));
    }

    private function cachePrefix(): string
    {
        return trim((string) config('store.ecount.cache_prefix', 'store_inventory'));
    }

    private function resolveBaseUrl(): string
    {
        $baseUrl = trim((string) config('store.ecount.base_url', ''));
        if ($baseUrl === '') {
            return $baseUrl;
        }

        // 잘못된 형식 자동 보정: https://oapi.BB.ecount.com → https://oapiBB.ecount.com (sboapi 동일)
        foreach (['oapi', 'sboapi'] as $prefix) {
            $pattern = '#^https?://'.preg_quote($prefix, '#').'\.([A-Za-z0-9]+)\.ecount\.com/?$#';
            if (preg_match($pattern, $baseUrl, $matches) === 1) {
                return 'https://'.$prefix.strtoupper($matches[1]).'.ecount.com';
            }
        }

        $zone = trim((string) config('store.ecount.zone', ''));
        if ($zone === '') {
            return $baseUrl;
        }

        $zoneUpper = strtoupper($zone);

        foreach (['{ZONE}' => $zoneUpper, '{zone}' => $zoneUpper] as $placeholder => $value) {
            if (str_contains($baseUrl, $placeholder)) {
                return str_replace($placeholder, $value, $baseUrl);
            }
        }

        return $baseUrl;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(mixed $payload): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        if (! is_array($payload)) {
            return [];
        }

        $data = $payload['Data'] ?? $payload['data'] ?? null;
        if (is_array($data)) {
            $result = $data['Result'] ?? $data['result'] ?? null;
            if (is_array($result) && array_is_list($result)) {
                return array_values(array_filter($result, 'is_array'));
            }
        }

        foreach (['data', 'items', 'results', 'list', 'rows', 'Result'] as $key) {
            $candidate = $payload[$key] ?? null;
            if (is_array($candidate) && array_is_list($candidate)) {
                return array_values(array_filter($candidate, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function pick(array $row, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                return $row[$key];
            }
        }

        return $default;
    }

    private function toInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return 0;
    }
}
