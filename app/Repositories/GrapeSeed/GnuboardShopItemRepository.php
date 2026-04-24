<?php

namespace App\Repositories\GrapeSeed;

use Illuminate\Support\Collection;
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
     * 상품코드별 그누보드 카테고리 경로(1~3단)와 그룹 키를 조회합니다. (SELECT 전용)
     *
     * @param  array<int, string>  $productCodes
     * @return array<string, array{category_l1:string,category_l2:string,category_l3:string,category_path:string,category_group_key:string}>
     */
    public function getCategoryPathMapByProductCodes(array $productCodes): array
    {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            return [];
        }

        $codes = $this->normalizeCodes($productCodes);
        if ($codes === []) {
            return [];
        }

        $l1 = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_category_l1_column', 'ca_id'));
        $l2 = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_category_l2_column', 'ca_id2'));
        $l3 = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.item_category_l3_column', 'ca_id3'));
        if ($l1 === '' && $l2 === '' && $l3 === '') {
            return [];
        }

        $categoryRows = $this->fetchCategoryRowsByCodeColumn($codes, $l1, $l2, $l3);
        if ($categoryRows === [] && $this->fallbackProductCodeColumn() !== '' && $this->fallbackProductCodeColumn() !== $this->productCodeColumn()) {
            $categoryRows = $this->fetchCategoryRowsByFallbackColumn($codes, $l1, $l2, $l3);
        }

        if ($categoryRows === []) {
            return [];
        }

        $categoryIds = [];
        foreach ($categoryRows as $row) {
            foreach (['l1', 'l2', 'l3'] as $key) {
                $id = $this->normalizeCategoryId((string) ($row[$key] ?? ''));
                if ($id !== '') {
                    $categoryIds[] = $id;
                }
            }
        }
        $categoryIds = array_values(array_unique($categoryIds));
        $nameMap = $categoryIds === [] ? [] : $this->fetchCategoryNameMap($categoryIds);

        return $this->buildCategoryPathMap($categoryRows, $nameMap);
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, array{l1:string,l2:string,l3:string}>
     */
    private function fetchCategoryRowsByCodeColumn(array $codes, string $l1, string $l2, string $l3): array
    {
        $codeColumn = $this->sanitizeSqlIdentifier($this->productCodeColumn());
        if ($codeColumn === '' || $codes === []) {
            return [];
        }

        $match = $this->normalizedCodeMatchSql($codeColumn);
        if ($match['sql'] === '') {
            return [];
        }

        $selectParts = ["{$match['sql']} as product_code"];
        $bindings = $match['bindings'];
        foreach ([['l1', $l1], ['l2', $l2], ['l3', $l3]] as [$alias, $col]) {
            if ($col !== '') {
                $selectParts[] = "`{$col}` as {$alias}";
            } else {
                $selectParts[] = "'' as {$alias}";
            }
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $whereBindings = array_merge($bindings, $codes);

        try {
            $rows = $this->itemTableQuery()
                ->selectRaw(implode(', ', $selectParts), $bindings)
                ->whereRaw("{$match['sql']} in ({$placeholders})", $whereBindings)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        return $this->mapCategoryRows($rows, $codes);
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, array{l1:string,l2:string,l3:string}>
     */
    private function fetchCategoryRowsByFallbackColumn(array $codes, string $l1, string $l2, string $l3): array
    {
        $fallback = $this->sanitizeSqlIdentifier($this->fallbackProductCodeColumn());
        if ($fallback === '' || $codes === []) {
            return [];
        }

        $match = $this->normalizedCodeMatchSql($fallback);
        if ($match['sql'] === '') {
            return [];
        }

        $selectParts = ["{$match['sql']} as product_code"];
        $bindings = $match['bindings'];
        foreach ([['l1', $l1], ['l2', $l2], ['l3', $l3]] as [$alias, $col]) {
            if ($col !== '') {
                $selectParts[] = "`{$col}` as {$alias}";
            } else {
                $selectParts[] = "'' as {$alias}";
            }
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $whereBindings = array_merge($bindings, $codes);

        try {
            $rows = $this->itemTableQuery()
                ->selectRaw(implode(', ', $selectParts), $bindings)
                ->whereRaw("{$match['sql']} in ({$placeholders})", $whereBindings)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        return $this->mapCategoryRows($rows, $codes);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string>  $codes
     * @return array<string, array{l1:string,l2:string,l3:string}>
     */
    private function mapCategoryRows($rows, array $codes): array
    {
        $map = [];
        foreach ($rows as $row) {
            $code = $this->normalizeProductCode((string) ($row->product_code ?? ''));
            if ($code === '' || ! in_array($code, $codes, true)) {
                continue;
            }
            $map[$code] = [
                'l1' => (string) ($row->l1 ?? ''),
                'l2' => (string) ($row->l2 ?? ''),
                'l3' => (string) ($row->l3 ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $categoryIds
     * @return array<string, string>
     */
    private function fetchCategoryNameMap(array $categoryIds): array
    {
        $table = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.category_table', 'g5_shop_category'));
        $idCol = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.category_id_column', 'ca_id'));
        $nameCol = $this->sanitizeSqlIdentifier((string) config('store.gnuboard.category_name_column', 'ca_name'));
        if ($table === '' || $idCol === '' || $nameCol === '' || $categoryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        try {
            $rows = $this->categoryTableQuery($table)
                ->selectRaw("`{$idCol}` as category_id, `{$nameCol}` as category_name")
                ->whereRaw("`{$idCol}` in ({$placeholders})", $categoryIds)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $id = $this->normalizeCategoryId((string) ($row->category_id ?? ''));
            if ($id === '') {
                continue;
            }
            $map[$id] = trim((string) ($row->category_name ?? ''));
        }

        return $map;
    }

    /**
     * @param  array<string, array{l1:string,l2:string,l3:string}>  $categoryRows
     * @param  array<string, string>  $nameMap
     * @return array<string, array{category_l1:string,category_l2:string,category_l3:string,category_path:string,category_group_key:string}>
     */
    private function buildCategoryPathMap(array $categoryRows, array $nameMap): array
    {
        $uncategorized = '미분류';
        $result = [];

        foreach ($categoryRows as $code => $levels) {
            $ids = [
                $this->normalizeCategoryId((string) ($levels['l1'] ?? '')),
                $this->normalizeCategoryId((string) ($levels['l2'] ?? '')),
                $this->normalizeCategoryId((string) ($levels['l3'] ?? '')),
            ];
            $labels = [];
            foreach ($ids as $id) {
                if ($id === '') {
                    continue;
                }
                $name = trim((string) ($nameMap[$id] ?? ''));
                $labels[] = $name !== '' ? $name : $id;
            }

            $path = $labels === [] ? $uncategorized : implode(' > ', $labels);
            $groupKey = implode('|', array_filter($ids, static fn (string $id): bool => $id !== ''));

            $result[$code] = [
                'category_l1' => $labels[0] ?? $uncategorized,
                'category_l2' => $labels[1] ?? '',
                'category_l3' => $labels[2] ?? '',
                'category_path' => $path,
                'category_group_key' => $groupKey !== '' ? $groupKey : $uncategorized,
            ];
        }

        return $result;
    }

    private function normalizeCategoryId(string $value): string
    {
        $value = trim($value);

        return $value;
    }

    private function categoryTableQuery(string $table)
    {
        return DB::connection($this->connectionName())->table($table);
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
