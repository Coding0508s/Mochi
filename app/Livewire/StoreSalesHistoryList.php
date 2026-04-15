<?php

namespace App\Livewire;

use App\Services\Store\StoreInventoryApiClient;
use Carbon\Carbon;
use Livewire\Component;
use Throwable;

class StoreSalesHistoryList extends Component
{
    private const MODAL_MAX_RANGE_DAYS = 90;

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public string $search = '';

    public int $page = 1;

    public int $perPage = 20;

    public ?string $loadError = null;

    /** 선택된 품목 행(`row_key`). 모달에 해당 품목의 histories를 표시한다. */
    public ?string $selectedRowKey = null;

    public bool $showSalesDetailModal = false;

    /** 모달 기간 조회 (YYYY-MM-DD) */
    public string $modalDateStart = '';

    public string $modalDateEnd = '';

    /** @var array<int, array<string, mixed>> */
    public array $modalDetailHistories = [];

    public ?string $modalHistoryError = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->loadError = null;

        try {
            /** @var StoreInventoryApiClient $client */
            $client = app(StoreInventoryApiClient::class);
            $hydrated = $this->hydrateRows($client->fetchSaleHistories(5));
            $this->items = array_values(array_filter(
                $hydrated,
                fn (array $row): bool => $this->isWithinSalesListWindow($row)
            ));
            $this->page = 1;
            $this->closeSalesDetailModal();
        } catch (Throwable $exception) {
            report($exception);
            $this->items = [];
            $this->loadError = '판매내역 데이터를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.';
            $this->closeSalesDetailModal();
        }
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->clearSelectionIfNotInFilter();
    }

    public function nextPage(): void
    {
        if ($this->page < $this->lastPage) {
            $this->page++;
            $this->closeSalesDetailModal();
        }
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->closeSalesDetailModal();
        }
    }

    /**
     * 마스터 테이블 행 클릭 시 판매·출고 내역을 모달로 연다. 같은 행을 다시 누르면 모달을 닫는다.
     */
    public function selectProductRow(string $rowKey): void
    {
        if ($rowKey === '') {
            return;
        }

        if ($this->showSalesDetailModal && $this->selectedRowKey === $rowKey) {
            $this->closeSalesDetailModal();

            return;
        }

        $this->selectedRowKey = $rowKey;
        $this->showSalesDetailModal = true;
        $this->modalHistoryError = null;
        $this->modalDateEnd = Carbon::now()->format('Y-m-d');
        $this->modalDateStart = Carbon::now()->subDays(6)->format('Y-m-d');

        $item = $this->selectedSalesItem;
        $this->modalDetailHistories = is_array($item) ? ($item['histories'] ?? []) : [];
    }

    public function closeSalesDetailModal(): void
    {
        $this->showSalesDetailModal = false;
        $this->selectedRowKey = null;
        $this->modalDetailHistories = [];
        $this->modalHistoryError = null;
        $this->modalDateStart = '';
        $this->modalDateEnd = '';
    }

    public function applyModalDateFilter(): void
    {
        $this->loadModalHistories();
    }

    private function loadModalHistories(): void
    {
        $this->modalHistoryError = null;

        $item = $this->selectedSalesItem;
        if (! is_array($item)) {
            $this->modalDetailHistories = [];

            return;
        }

        $code = strtoupper(trim((string) ($item['product_code'] ?? '')));
        if ($code === '') {
            $this->modalHistoryError = '상품코드가 없어 조회할 수 없습니다.';
            $this->modalDetailHistories = [];

            return;
        }

        try {
            $start = Carbon::parse($this->modalDateStart)->startOfDay();
            $end = Carbon::parse($this->modalDateEnd)->endOfDay();
        } catch (Throwable) {
            $this->modalHistoryError = '시작일·종료일 형식이 올바르지 않습니다. (YYYY-MM-DD)';
            $this->modalDetailHistories = [];

            return;
        }

        if ($end->lessThan($start)) {
            $this->modalHistoryError = '종료일은 시작일과 같거나 이후여야 합니다.';
            $this->modalDetailHistories = [];

            return;
        }

        $inclusiveDays = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        if ($inclusiveDays < 1 || $inclusiveDays > self::MODAL_MAX_RANGE_DAYS) {
            $this->modalHistoryError = '조회 기간은 1일 이상 '.self::MODAL_MAX_RANGE_DAYS.'일 이하여야 합니다.';
            $this->modalDetailHistories = [];

            return;
        }

        try {
            $rows = app(StoreInventoryApiClient::class)->fetchSaleHistoriesForProductInDateRange(
                $code,
                $start,
                $end,
                300,
            );
            $this->modalDetailHistories = array_map(
                fn (array $history): array => $this->mapHistoryRowForDisplay($history),
                $rows
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->modalHistoryError = '기간별 내역을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.';
            $this->modalDetailHistories = [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredItemsProperty(): array
    {
        $keyword = mb_strtolower(trim($this->search));
        if ($keyword === '') {
            return $this->items;
        }

        return array_values(array_filter($this->items, function (array $item) use ($keyword): bool {
            $code = mb_strtolower((string) ($item['product_code'] ?? ''));
            $name = mb_strtolower((string) ($item['product_name'] ?? ''));

            return str_contains($code, $keyword) || str_contains($name, $keyword);
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaginatedItemsProperty(): array
    {
        $offset = max(0, ($this->page - 1) * $this->perPage);

        return array_slice($this->filteredItems, $offset, $this->perPage);
    }

    public function getTotalItemsProperty(): int
    {
        return count($this->filteredItems);
    }

    public function getLastPageProperty(): int
    {
        if ($this->totalItems <= 0) {
            return 1;
        }

        return (int) ceil($this->totalItems / $this->perPage);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSelectedSalesItemProperty(): ?array
    {
        if ($this->selectedRowKey === null || $this->selectedRowKey === '') {
            return null;
        }

        foreach ($this->filteredItems as $item) {
            if (($item['row_key'] ?? '') === $this->selectedRowKey) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{history_rows: int, total_qty: int}
     */
    public function getMasterSummaryProperty(): array
    {
        $historyRows = 0;
        $totalQty = 0;
        foreach ($this->paginatedItems as $item) {
            $historyRows += (int) ($item['history_count'] ?? 0);
            $totalQty += (int) ($item['history_total_qty'] ?? 0);
        }

        return [
            'history_rows' => $historyRows,
            'total_qty' => $totalQty,
        ];
    }

    public function getSalesListDateRangeLabelProperty(): string
    {
        $end = Carbon::now();
        $start = $end->copy()->subDays(self::MODAL_MAX_RANGE_DAYS)->startOfDay();

        return $start->format('Y-m-d').' ~ '.$end->format('Y-m-d');
    }

    private function clearSelectionIfNotInFilter(): void
    {
        if ($this->selectedRowKey === null) {
            return;
        }

        foreach ($this->filteredItems as $item) {
            if (($item['row_key'] ?? '') === $this->selectedRowKey) {
                return;
            }
        }

        $this->closeSalesDetailModal();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $histories = is_array($row['histories'] ?? null) ? $row['histories'] : [];
            $row['row_key'] = 'sale_'.md5((string) ($row['product_code'] ?? ''));
            $row['history_latest_occurred_at_raw'] = trim((string) (($histories[0]['at'] ?? '') ?: ''));
            $mapped = array_map(fn (array $history): array => $this->mapHistoryRowForDisplay($history), $histories);

            $row['histories'] = $mapped;
            $row['history_count'] = count($mapped);
            $totalQty = 0;
            foreach ($mapped as $h) {
                $totalQty += (int) ($h['qty'] ?? 0);
            }
            $row['history_total_qty'] = $totalQty;
            $row['history_latest_at'] = $mapped[0]['at'] ?? '-';

            return $row;
        }, $rows);
    }

    /**
     * 판매내역 목록은 조회 기준 최대 90일 범위만 노출한다.
     *
     * @param  array<string, mixed>  $row
     */
    private function isWithinSalesListWindow(array $row): bool
    {
        $latestRaw = trim((string) ($row['history_latest_occurred_at_raw'] ?? ''));
        if ($latestRaw === '') {
            return false;
        }

        try {
            $latest = Carbon::parse($latestRaw);
        } catch (Throwable) {
            return false;
        }

        $cutoff = Carbon::now()->subDays(self::MODAL_MAX_RANGE_DAYS)->startOfDay();

        return $latest->greaterThanOrEqualTo($cutoff);
    }

    private function formatDateTime(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '-';
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d H:i');
        } catch (Throwable) {
            return $raw;
        }
    }

    /**
     * @param  array<string, mixed>  $history
     * @return array<string, mixed>
     */
    private function mapHistoryRowForDisplay(array $history): array
    {
        $qty = is_numeric($history['qty'] ?? null) ? (int) $history['qty'] : 0;

        return [
            'qty' => $qty,
            'qty_display' => $qty > 0 ? '-'.number_format($qty) : '-',
            'at' => $this->formatDateTime((string) ($history['at'] ?? '')),
            'type' => trim((string) ($history['type'] ?? '')),
            'ref' => trim((string) ($history['ref'] ?? '')),
            'reason' => trim((string) ($history['reason'] ?? '')),
            'order_customer_name' => trim((string) ($history['order_customer_name'] ?? '')),
        ];
    }

    public function render()
    {
        return view('livewire.store-sales-history-list', [
            'paginatedItems' => $this->paginatedItems,
            'selectedSalesItem' => $this->selectedSalesItem,
            'masterSummary' => $this->masterSummary,
            'salesListDateRangeLabel' => $this->salesListDateRangeLabel,
        ]);
    }
}
