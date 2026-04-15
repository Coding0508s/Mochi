<?php

namespace App\Repositories\GrapeSeed;

use Illuminate\Support\Facades\DB;
use Throwable;

class GnuboardShopItemRepository
{
    /**
     * @param  array<int, string>  $productCodes
     * @return array<string, int> product_code => notify_quantity
     */
    public function getNotifyQuantityMapByProductCodes(array $productCodes): array
    {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            return [];
        }

        $codes = $this->normalizeCodes($productCodes);
        if ($codes === []) {
            return [];
        }

        $notifyQtyColumn = (string) config('store.gnuboard.notify_quantity_column', 'it_noti_qty');

        return $this->fetchIntMapByCodes($codes, $notifyQtyColumn);
    }

    /**
     * @param  array<int, string>  $productCodes
     * @return array<string, int> product_code => actual_stock_quantity
     */
    public function getStockQuantityMapByProductCodes(array $productCodes): array
    {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            return [];
        }

        $codes = $this->normalizeCodes($productCodes);
        if ($codes === []) {
            return [];
        }

        $stockQtyColumn = (string) config('store.gnuboard.stock_quantity_column', 'it_stock_qty');

        return $this->fetchIntMapByCodes($codes, $stockQtyColumn);
    }

    /**
     * `it_model`(및 설정된 폴백 컬럼) 기준으로 그누보드 상품명을 조회합니다. 품목코드는 저장소와 동일하게 정규화합니다.
     *
     * @param  array<int, string>  $productCodes
     * @return array<string, string> normalized_product_code => it_name (trimmed)
     */
    public function getProductNameMapByProductCodes(array $productCodes): array
    {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            return [];
        }

        $codes = $this->normalizeCodes($productCodes);
        if ($codes === []) {
            return [];
        }

        $nameColumn = (string) config('store.gnuboard.item_name_column', 'it_name');

        return $this->fetchStringMapByCodes($codes, $nameColumn);
    }

    /**
     * @return array{product_code:string,before_qty:int,after_qty:int,updated:bool}
     */
    public function updateStockQuantityByProductCode(string $productCode, int $quantity): array
    {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            return [
                'product_code' => '',
                'before_qty' => 0,
                'after_qty' => 0,
                'updated' => false,
            ];
        }

        $code = $this->normalizeProductCode($productCode);
        if ($code === '') {
            return [
                'product_code' => '',
                'before_qty' => 0,
                'after_qty' => 0,
                'updated' => false,
            ];
        }

        $safeQuantity = max(0, $quantity);
        $row = $this->resolveStockRowForCode($code);
        if ($row === null) {
            return [
                'product_code' => $code,
                'before_qty' => 0,
                'after_qty' => $safeQuantity,
                'updated' => false,
            ];
        }

        $stockQtyColumn = (string) config('store.gnuboard.stock_quantity_column', 'it_stock_qty');
        $beforeQty = (int) ($row[$stockQtyColumn] ?? 0);

        $updated = $this->itemTableQuery()
            ->where('it_id', (string) ($row['it_id'] ?? ''))
            ->update([$stockQtyColumn => $safeQuantity]) > 0;

        return [
            'product_code' => $code,
            'before_qty' => max(0, $beforeQty),
            'after_qty' => $safeQuantity,
            'updated' => $updated,
        ];
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, int>
     */
    private function fetchIntMapByCodes(array $codes, string $valueColumn): array
    {
        $primaryMap = $this->fetchMapByColumn($codes, $this->productCodeColumn(), $valueColumn);
        $fallbackColumn = $this->fallbackProductCodeColumn();
        if ($fallbackColumn === '' || $fallbackColumn === $this->productCodeColumn()) {
            return $primaryMap;
        }

        $missing = array_values(array_filter(
            $codes,
            static fn (string $code): bool => ! array_key_exists($code, $primaryMap)
        ));
        if ($missing === []) {
            return $primaryMap;
        }

        $fallbackMap = $this->fetchMapByColumn($missing, $fallbackColumn, $valueColumn);

        return $primaryMap + $fallbackMap;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, string>
     */
    private function fetchStringMapByCodes(array $codes, string $valueColumn): array
    {
        $primaryMap = $this->fetchStringMapByColumn($codes, $this->productCodeColumn(), $valueColumn);
        $fallbackColumn = $this->fallbackProductCodeColumn();
        if ($fallbackColumn === '' || $fallbackColumn === $this->productCodeColumn()) {
            return $primaryMap;
        }

        $missing = array_values(array_filter(
            $codes,
            static fn (string $code): bool => ! array_key_exists($code, $primaryMap)
        ));
        if ($missing === []) {
            return $primaryMap;
        }

        $fallbackMap = $this->fetchStringMapByColumn($missing, $fallbackColumn, $valueColumn);

        return $primaryMap + $fallbackMap;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, int>
     */
    private function fetchMapByColumn(array $codes, string $codeColumn, string $valueColumn): array
    {
        if ($codeColumn === '' || $codes === []) {
            return [];
        }

        $safeCode = $this->sanitizeSqlIdentifier($codeColumn);
        $safeVal = $this->sanitizeSqlIdentifier($valueColumn);
        if ($safeCode === '' || $safeVal === '') {
            return [];
        }

        $match = $this->normalizedCodeMatchSql($safeCode);
        if ($match['sql'] === '') {
            return [];
        }

        $sql = $match['sql'];
        $replaceBindings = $match['bindings'];
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $whereBindings = array_merge($replaceBindings, $codes);

        try {
            $rows = $this->itemTableQuery()
                ->selectRaw("{$sql} as product_code, `{$safeVal}` as value_int", $replaceBindings)
                ->whereRaw("{$sql} in ({$placeholders})", $whereBindings)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $code = $this->normalizeProductCode((string) ($row->product_code ?? ''));
            if ($code === '' || ! in_array($code, $codes, true)) {
                continue;
            }
            $map[$code] = max(0, (int) ($row->value_int ?? 0));
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, string>
     */
    private function fetchStringMapByColumn(array $codes, string $codeColumn, string $valueColumn): array
    {
        if ($codeColumn === '' || $codes === []) {
            return [];
        }

        $safeCode = $this->sanitizeSqlIdentifier($codeColumn);
        $safeVal = $this->sanitizeSqlIdentifier($valueColumn);
        if ($safeCode === '' || $safeVal === '') {
            return [];
        }

        $match = $this->normalizedCodeMatchSql($safeCode);
        if ($match['sql'] === '') {
            return [];
        }

        $sql = $match['sql'];
        $replaceBindings = $match['bindings'];
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $whereBindings = array_merge($replaceBindings, $codes);

        try {
            $rows = $this->itemTableQuery()
                ->selectRaw("{$sql} as product_code, `{$safeVal}` as value_str", $replaceBindings)
                ->whereRaw("{$sql} in ({$placeholders})", $whereBindings)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $code = $this->normalizeProductCode((string) ($row->product_code ?? ''));
            if ($code === '' || ! in_array($code, $codes, true)) {
                continue;
            }
            $map[$code] = trim((string) ($row->value_str ?? ''));
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStockRowForCode(string $code): ?array
    {
        $stockQtyColumn = (string) config('store.gnuboard.stock_quantity_column', 'it_stock_qty');
        $columns = ['it_id', $this->productCodeColumn(), $this->fallbackProductCodeColumn(), $stockQtyColumn];
        $columns = array_values(array_unique(array_filter($columns, static fn (string $col): bool => $col !== '')));

        $primary = $this->normalizedCodeMatchSql($this->sanitizeSqlIdentifier($this->productCodeColumn()));
        if ($primary['sql'] === '') {
            return null;
        }

        $row = $this->itemTableQuery()
            ->select($columns)
            ->whereRaw($primary['sql'].' = ?', array_merge($primary['bindings'], [$code]))
            ->first();

        if ($row === null && $this->fallbackProductCodeColumn() !== '' && $this->fallbackProductCodeColumn() !== $this->productCodeColumn()) {
            $fallback = $this->normalizedCodeMatchSql($this->sanitizeSqlIdentifier($this->fallbackProductCodeColumn()));
            if ($fallback['sql'] === '') {
                return null;
            }
            $row = $this->itemTableQuery()
                ->select($columns)
                ->whereRaw($fallback['sql'].' = ?', array_merge($fallback['bindings'], [$code]))
                ->first();
        }

        return $row !== null ? (array) $row : null;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private function normalizeCodes(array $codes): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $c): string => $this->normalizeProductCode($c),
            $codes
        ), static fn (string $c): bool => $c !== '')));
    }

    private function normalizeProductCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/^[\p{Zs}]+|[\p{Zs}]+$/u', '', $code) ?? '';

        return $code;
    }

    /**
     * @return array{sql:string, bindings:array{0:string,1:string}}
     */
    private function normalizedCodeMatchSql(string $safeColumn): array
    {
        if ($safeColumn === '') {
            return ['sql' => '', 'bindings' => []];
        }

        $bindings = ["\u{3000}", "\u{00A0}"];

        return [
            'sql' => "UPPER(TRIM(REPLACE(REPLACE(`{$safeColumn}`, ?, ''), ?, '')))",
            'bindings' => $bindings,
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

    private function itemTable(): string
    {
        return (string) config('store.gnuboard.item_table', 'g5_shop_item');
    }

    private function productCodeColumn(): string
    {
        return (string) config('store.gnuboard.product_code_column', 'it_model');
    }

    private function fallbackProductCodeColumn(): string
    {
        return (string) config('store.gnuboard.fallback_product_code_column', 'it_id');
    }

    private function itemTableQuery()
    {
        return DB::connection($this->connectionName())->table($this->itemTable());
    }
}
