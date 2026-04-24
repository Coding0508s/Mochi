<?php

namespace App\Repositories\GrapeSeed;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class GnuboardSalesHistoryRepository
{
    /**
     * @param  array<int, string>  $productCodes
     * @return array<int, array{product_code:string,product_name:string,histories:array<int, array{qty:int,at:string,type:string,reason:string,ref:string,order_customer_name:string}>}>
     */
    public function getRecentSaleHistoriesByProductCodes(array $productCodes, ?int $limitPerProduct = null): array
    {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            return [];
        }

        $codes = $this->normalizeCodes($productCodes);
        if ($codes === []) {
            return [];
        }

        $maxHistories = max(1, $limitPerProduct ?? (int) config('store.gnuboard.sales.max_histories_per_product', 5));
        $rows = $this->fetchHistoryRows($codes);

        $namesByCode = [];
        $historiesByCode = [];

        foreach ($rows as $row) {
            $code = $this->normalizeProductCode((string) ($row->product_code ?? ''));
            if ($code === '' || ! in_array($code, $codes, true)) {
                continue;
            }

            if (count($historiesByCode[$code] ?? []) >= $maxHistories) {
                continue;
            }

            $name = trim((string) ($row->product_name ?? ''));
            if ($name !== '' && ! array_key_exists($code, $namesByCode)) {
                $namesByCode[$code] = $name;
            }

            $qty = max(0, (int) ($row->qty_raw ?? 0));
            if ($qty <= 0) {
                continue;
            }

            $historiesByCode[$code] ??= [];
            $historiesByCode[$code][] = [
                'qty' => $qty,
                'at' => trim((string) ($row->sold_at ?? '')),
                'type' => trim((string) ($row->order_status ?? '')),
                'reason' => trim((string) ($row->order_reason ?? '')),
                'ref' => trim((string) ($row->order_ref ?? '')),
                'order_customer_name' => trim((string) ($row->order_customer_name ?? '')),
            ];
        }

        return array_map(function (string $code) use ($historiesByCode, $namesByCode): array {
            return [
                'product_code' => $code,
                'product_name' => (string) ($namesByCode[$code] ?? $code),
                'histories' => $historiesByCode[$code] ?? [],
            ];
        }, $codes);
    }

    /**
     * 단일 품목의 판매 라인을 주문 일시 구간으로 조회합니다(모달 기간 필터용).
     *
     * @return array<int, array{qty:int,at:string,type:string,reason:string,ref:string,order_customer_name:string}>
     */
    public function getSaleHistoriesForProductCodeBetween(
        string $productCode,
        CarbonInterface $startInclusive,
        CarbonInterface $endInclusive,
        int $limit = 300,
    ): array {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            return [];
        }

        $code = $this->normalizeProductCode($productCode);
        if ($code === '') {
            return [];
        }

        $start = CarbonImmutable::parse($startInclusive)->startOfDay();
        $end = CarbonImmutable::parse($endInclusive)->endOfDay();
        if ($end->lessThan($start)) {
            return [];
        }

        $maxRows = max(1, min($limit, (int) config('store.gnuboard.sales.max_rows_per_query', 5000)));
        $rows = $this->fetchHistoryRowsForCodeBetween($code, $start, $end, $maxRows);
        $out = [];
        foreach ($rows as $row) {
            $qty = max(0, (int) ($row->qty_raw ?? 0));
            if ($qty <= 0) {
                continue;
            }

            $out[] = [
                'qty' => $qty,
                'at' => trim((string) ($row->sold_at ?? '')),
                'type' => trim((string) ($row->order_status ?? '')),
                'reason' => trim((string) ($row->order_reason ?? '')),
                'ref' => trim((string) ($row->order_ref ?? '')),
                'order_customer_name' => trim((string) ($row->order_customer_name ?? '')),
            ];
        }

        return $out;
    }

    /**
     * 전체 주문 내역을 최신순으로 페이지네이션하여 조회합니다.
     */
    public function getPaginatedAllSaleHistories(
        ?string $search,
        ?CarbonInterface $startDate,
        ?CarbonInterface $endDate,
        int $perPage = 20
    ): LengthAwarePaginator {
        $safePerPage = max(1, $perPage);

        if (! (bool) config('store.gnuboard.enabled', true)) {
            return $this->emptyPaginator($safePerPage);
        }

        $orderTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_table', 'g5_shop_order'));
        $cartTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_table', 'g5_shop_cart'));
        $itemTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_table', 'g5_shop_item'));
        $orderIdColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_id_column', 'od_id'));
        $orderDatetimeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_datetime_column', 'od_time'));
        $orderStatusColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_status_column', 'od_status'));
        $orderSettleCaseColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_settle_case_column', 'od_settle_case'));
        $orderCustomerNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_customer_name_column', 'od_name'));
        $cartProductIdColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_product_id_column', 'it_id'));
        $cartQuantityColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_quantity_column', 'ct_qty'));
        $cartNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_name_column', 'it_name'));
        $cartStatusColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_status_column', 'ct_status'));
        $itemProductCodeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.product_code_column', 'it_model'));
        $itemFallbackCodeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.fallback_product_code_column', 'it_id'));
        $itemNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_name_column', 'it_name'));

        if (
            $orderTable === '' || $cartTable === '' || $itemTable === '' || $orderIdColumn === ''
            || $orderDatetimeColumn === '' || $cartProductIdColumn === '' || $cartQuantityColumn === ''
            || $itemProductCodeColumn === '' || $itemFallbackCodeColumn === '' || $itemNameColumn === ''
        ) {
            return $this->emptyPaginator($safePerPage);
        }

        $codeExpression = "COALESCE(i.`{$itemProductCodeColumn}`, c.`{$cartProductIdColumn}`)";
        $normalizedCodeSql = $this->normalizedSqlForExpression($codeExpression);
        $productNameSql = $cartNameColumn !== ''
            ? "COALESCE(i.`{$itemNameColumn}`, c.`{$cartNameColumn}`, '')"
            : "COALESCE(i.`{$itemNameColumn}`, '')";

        try {
            $query = DB::connection($this->connectionName())
                ->table("{$cartTable} as c")
                ->join("{$orderTable} as o", "o.{$orderIdColumn}", '=', "c.{$orderIdColumn}")
                ->leftJoin("{$itemTable} as i", "i.{$itemFallbackCodeColumn}", '=', "c.{$cartProductIdColumn}")
                ->selectRaw("{$normalizedCodeSql['sql']} as product_code", $normalizedCodeSql['bindings'])
                ->selectRaw("{$productNameSql} as product_name")
                ->selectRaw("c.`{$cartQuantityColumn}` as qty")
                ->selectRaw("o.`{$orderDatetimeColumn}` as sold_at")
                ->selectRaw(($orderStatusColumn !== '' ? "o.`{$orderStatusColumn}`" : "''").' as order_status')
                ->selectRaw("o.`{$orderIdColumn}` as order_ref")
                ->selectRaw(($orderSettleCaseColumn !== '' ? "o.`{$orderSettleCaseColumn}`" : "''").' as order_reason')
                ->selectRaw(($orderCustomerNameColumn !== '' ? "o.`{$orderCustomerNameColumn}`" : "''").' as order_customer_name')
                ->where("c.{$cartQuantityColumn}", '>', 0);

            $excludedOrderStatuses = $this->normalizeStatusFilters((array) config('store.gnuboard.sales.excluded_order_statuses', []));
            if ($excludedOrderStatuses !== [] && $orderStatusColumn !== '') {
                $query->where(function ($builder) use ($excludedOrderStatuses, $orderStatusColumn): void {
                    $builder
                        ->whereNull("o.{$orderStatusColumn}")
                        ->orWhereNotIn("o.{$orderStatusColumn}", $excludedOrderStatuses);
                });
            }

            $excludedCartStatuses = $this->normalizeStatusFilters((array) config('store.gnuboard.sales.excluded_cart_statuses', []));
            if ($excludedCartStatuses !== [] && $cartStatusColumn !== '') {
                $query->where(function ($builder) use ($excludedCartStatuses, $cartStatusColumn): void {
                    $builder
                        ->whereNull("c.{$cartStatusColumn}")
                        ->orWhereNotIn("c.{$cartStatusColumn}", $excludedCartStatuses);
                });
            }

            if ($startDate !== null) {
                $query->where("o.{$orderDatetimeColumn}", '>=', CarbonImmutable::parse($startDate)->startOfDay()->format('Y-m-d H:i:s'));
            }

            if ($endDate !== null) {
                $query->where("o.{$orderDatetimeColumn}", '<=', CarbonImmutable::parse($endDate)->endOfDay()->format('Y-m-d H:i:s'));
            }

            $searchKeyword = trim((string) $search);
            if ($searchKeyword !== '') {
                $query->where(function ($builder) use ($searchKeyword, $productNameSql, $orderCustomerNameColumn, $orderIdColumn): void {
                    $builder
                        ->whereRaw("{$productNameSql} LIKE ?", ["%{$searchKeyword}%"])
                        ->orWhere("o.{$orderIdColumn}", 'LIKE', "%{$searchKeyword}%");

                    if ($orderCustomerNameColumn !== '') {
                        $builder->orWhere("o.{$orderCustomerNameColumn}", 'LIKE', "%{$searchKeyword}%");
                    }
                });
            }

            return $query
                ->orderByDesc("o.{$orderDatetimeColumn}")
                ->paginate($safePerPage);
        } catch (Throwable $exception) {
            report($exception);

            return $this->emptyPaginator($safePerPage);
        }
    }

    /**
     * @return array<int, object>
     */
    private function fetchHistoryRowsForCodeBetween(string $normalizedCode, CarbonImmutable $start, CarbonImmutable $end, int $limit): array
    {
        $orderTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_table', 'g5_shop_order'));
        $cartTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_table', 'g5_shop_cart'));
        $itemTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_table', 'g5_shop_item'));
        $orderIdColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_id_column', 'od_id'));
        $orderDatetimeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_datetime_column', 'od_time'));
        $orderStatusColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_status_column', 'od_status'));
        $orderSettleCaseColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_settle_case_column', 'od_settle_case'));
        $orderCustomerNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_customer_name_column', 'od_name'));
        $cartProductIdColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_product_id_column', 'it_id'));
        $cartQuantityColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_quantity_column', 'ct_qty'));
        $cartNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_name_column', 'it_name'));
        $cartStatusColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_status_column', 'ct_status'));
        $itemProductCodeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.product_code_column', 'it_model'));
        $itemFallbackCodeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.fallback_product_code_column', 'it_id'));
        $itemNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_name_column', 'it_name'));

        if (
            $orderTable === '' || $cartTable === '' || $itemTable === '' || $orderIdColumn === ''
            || $orderDatetimeColumn === '' || $cartProductIdColumn === '' || $cartQuantityColumn === ''
            || $itemProductCodeColumn === '' || $itemFallbackCodeColumn === '' || $itemNameColumn === ''
        ) {
            return [];
        }

        $codeExpression = "COALESCE(i.`{$itemProductCodeColumn}`, c.`{$cartProductIdColumn}`)";
        $normalizedCodeSql = $this->normalizedSqlForExpression($codeExpression);
        $productNameSql = $cartNameColumn !== ''
            ? "COALESCE(i.`{$itemNameColumn}`, c.`{$cartNameColumn}`, '')"
            : "COALESCE(i.`{$itemNameColumn}`, '')";

        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        try {
            $query = DB::connection($this->connectionName())
                ->table("{$cartTable} as c")
                ->join("{$orderTable} as o", "o.{$orderIdColumn}", '=', "c.{$orderIdColumn}")
                ->leftJoin("{$itemTable} as i", "i.{$itemFallbackCodeColumn}", '=', "c.{$cartProductIdColumn}")
                ->selectRaw("{$normalizedCodeSql['sql']} as product_code", $normalizedCodeSql['bindings'])
                ->selectRaw("{$productNameSql} as product_name")
                ->selectRaw("c.`{$cartQuantityColumn}` as qty_raw")
                ->selectRaw("o.`{$orderDatetimeColumn}` as sold_at")
                ->selectRaw(($orderStatusColumn !== '' ? "o.`{$orderStatusColumn}`" : "''").' as order_status')
                ->selectRaw("o.`{$orderIdColumn}` as order_ref")
                ->selectRaw(($orderSettleCaseColumn !== '' ? "o.`{$orderSettleCaseColumn}`" : "''").' as order_reason')
                ->selectRaw(($orderCustomerNameColumn !== '' ? "o.`{$orderCustomerNameColumn}`" : "''").' as order_customer_name')
                ->whereRaw("{$normalizedCodeSql['sql']} = ?", array_merge($normalizedCodeSql['bindings'], [$normalizedCode]))
                ->whereBetween("o.{$orderDatetimeColumn}", [$startStr, $endStr])
                ->orderByDesc("o.{$orderDatetimeColumn}")
                ->limit($limit);

            $excludedOrderStatuses = $this->normalizeStatusFilters((array) config('store.gnuboard.sales.excluded_order_statuses', []));
            if ($excludedOrderStatuses !== [] && $orderStatusColumn !== '') {
                $query->where(function ($builder) use ($excludedOrderStatuses, $orderStatusColumn): void {
                    $builder
                        ->whereNull("o.{$orderStatusColumn}")
                        ->orWhereNotIn("o.{$orderStatusColumn}", $excludedOrderStatuses);
                });
            }

            $excludedCartStatuses = $this->normalizeStatusFilters((array) config('store.gnuboard.sales.excluded_cart_statuses', []));
            if ($excludedCartStatuses !== [] && $cartStatusColumn !== '') {
                $query->where(function ($builder) use ($excludedCartStatuses, $cartStatusColumn): void {
                    $builder
                        ->whereNull("c.{$cartStatusColumn}")
                        ->orWhereNotIn("c.{$cartStatusColumn}", $excludedCartStatuses);
                });
            }

            return $query->get()->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, object>
     */
    private function fetchHistoryRows(array $codes): array
    {
        $orderTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_table', 'g5_shop_order'));
        $cartTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_table', 'g5_shop_cart'));
        $itemTable = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_table', 'g5_shop_item'));
        $orderIdColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_id_column', 'od_id'));
        $orderDatetimeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_datetime_column', 'od_time'));
        $orderStatusColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_status_column', 'od_status'));
        $orderSettleCaseColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_settle_case_column', 'od_settle_case'));
        $orderCustomerNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.order_customer_name_column', 'od_name'));
        $cartProductIdColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_product_id_column', 'it_id'));
        $cartQuantityColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_quantity_column', 'ct_qty'));
        $cartNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_name_column', 'it_name'));
        $cartStatusColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.sales.cart_status_column', 'ct_status'));
        $itemProductCodeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.product_code_column', 'it_model'));
        $itemFallbackCodeColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.fallback_product_code_column', 'it_id'));
        $itemNameColumn = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_name_column', 'it_name'));

        if (
            $orderTable === '' || $cartTable === '' || $itemTable === '' || $orderIdColumn === ''
            || $orderDatetimeColumn === '' || $cartProductIdColumn === '' || $cartQuantityColumn === ''
            || $itemProductCodeColumn === '' || $itemFallbackCodeColumn === '' || $itemNameColumn === ''
        ) {
            return [];
        }

        $codeExpression = "COALESCE(i.`{$itemProductCodeColumn}`, c.`{$cartProductIdColumn}`)";
        $normalizedCode = $this->normalizedSqlForExpression($codeExpression);
        $productNameSql = $cartNameColumn !== ''
            ? "COALESCE(i.`{$itemNameColumn}`, c.`{$cartNameColumn}`, '')"
            : "COALESCE(i.`{$itemNameColumn}`, '')";
        $placeholders = implode(', ', array_fill(0, count($codes), '?'));
        $lookbackDays = max(1, (int) config('store.gnuboard.sales.lookback_days', 180));
        $cutoff = CarbonImmutable::now()->subDays($lookbackDays)->format('Y-m-d H:i:s');
        $maxRows = max(1, (int) config('store.gnuboard.sales.max_rows_per_query', 5000));

        try {
            $query = DB::connection($this->connectionName())
                ->table("{$cartTable} as c")
                ->join("{$orderTable} as o", "o.{$orderIdColumn}", '=', "c.{$orderIdColumn}")
                ->leftJoin("{$itemTable} as i", "i.{$itemFallbackCodeColumn}", '=', "c.{$cartProductIdColumn}")
                ->selectRaw("{$normalizedCode['sql']} as product_code", $normalizedCode['bindings'])
                ->selectRaw("{$productNameSql} as product_name")
                ->selectRaw("c.`{$cartQuantityColumn}` as qty_raw")
                ->selectRaw("o.`{$orderDatetimeColumn}` as sold_at")
                ->selectRaw(($orderStatusColumn !== '' ? "o.`{$orderStatusColumn}`" : "''").' as order_status')
                ->selectRaw("o.`{$orderIdColumn}` as order_ref")
                ->selectRaw(($orderSettleCaseColumn !== '' ? "o.`{$orderSettleCaseColumn}`" : "''").' as order_reason')
                ->selectRaw(($orderCustomerNameColumn !== '' ? "o.`{$orderCustomerNameColumn}`" : "''").' as order_customer_name')
                ->whereRaw("{$normalizedCode['sql']} in ({$placeholders})", array_merge($normalizedCode['bindings'], $codes))
                ->where("o.{$orderDatetimeColumn}", '>=', $cutoff)
                ->orderByDesc("o.{$orderDatetimeColumn}")
                ->limit($maxRows);

            $excludedOrderStatuses = $this->normalizeStatusFilters((array) config('store.gnuboard.sales.excluded_order_statuses', []));
            if ($excludedOrderStatuses !== [] && $orderStatusColumn !== '') {
                $query->where(function ($builder) use ($excludedOrderStatuses, $orderStatusColumn): void {
                    $builder
                        ->whereNull("o.{$orderStatusColumn}")
                        ->orWhereNotIn("o.{$orderStatusColumn}", $excludedOrderStatuses);
                });
            }

            $excludedCartStatuses = $this->normalizeStatusFilters((array) config('store.gnuboard.sales.excluded_cart_statuses', []));
            if ($excludedCartStatuses !== [] && $cartStatusColumn !== '') {
                $query->where(function ($builder) use ($excludedCartStatuses, $cartStatusColumn): void {
                    $builder
                        ->whereNull("c.{$cartStatusColumn}")
                        ->orWhereNotIn("c.{$cartStatusColumn}", $excludedCartStatuses);
                });
            }

            return $query->get()->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function normalizeCodes(array $codes): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $code): string => $this->normalizeProductCode($code),
            $codes
        ), static fn (string $code): bool => $code !== '')));
    }

    private function normalizeProductCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/^[\p{Zs}]+|[\p{Zs}]+$/u', '', $code) ?? '';

        return $code;
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array<int, string>
     */
    private function normalizeStatusFilters(array $statuses): array
    {
        return array_values(array_filter(array_map(
            static fn (string $status): string => trim($status),
            $statuses
        ), static fn (string $status): bool => $status !== ''));
    }

    /**
     * @return array{sql:string, bindings:array{0:string,1:string}}
     */
    private function normalizedSqlForExpression(string $expression): array
    {
        return [
            'sql' => "UPPER(TRIM(REPLACE(REPLACE({$expression}, ?, ''), ?, '')))",
            'bindings' => ["\u{3000}", "\u{00A0}"],
        ];
    }

    private function sanitizeSqlIdentifier(string $identifier): string
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $identifier) === 1 ? $identifier : '';
    }

    private function connectionName(): string
    {
        return (string) config('store.gnuboard.connection', 'mysql_grapeseed_goods');
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            max(1, $perPage),
            null,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }
}
