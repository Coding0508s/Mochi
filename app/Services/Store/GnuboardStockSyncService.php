<?php

namespace App\Services\Store;

use App\Models\StoreGnuboardStockChangeLog;
use App\Repositories\GrapeSeed\GnuboardShopItemRepository;
use Carbon\Carbon;
use RuntimeException;

final class GnuboardStockSyncService
{
    public function __construct(
        private readonly GnuboardShopItemRepository $gnuboardShopItemRepository,
    ) {}

    /**
     * @return array{product_code:string,before_qty:int,after_qty:int,updated:bool}
     */
    public function updateActualStockQuantity(string $productCode, int $quantity, ?int $changedBy, ?string $memo = null): array
    {
        $updated = $this->gnuboardShopItemRepository->updateStockQuantityByProductCode($productCode, $quantity);
        if (! ($updated['updated'] ?? false)) {
            throw new RuntimeException('그누보드 실제수량을 수정할 대상을 찾지 못했습니다.');
        }

        StoreGnuboardStockChangeLog::query()->create([
            'product_code' => (string) ($updated['product_code'] ?? strtoupper(trim($productCode))),
            'before_qty' => max(0, (int) ($updated['before_qty'] ?? 0)),
            'after_qty' => max(0, (int) ($updated['after_qty'] ?? 0)),
            'changed_by' => $changedBy,
            'source' => 'store_inventory',
            'memo' => $memo !== null ? trim($memo) : null,
        ]);

        return [
            'product_code' => (string) ($updated['product_code'] ?? strtoupper(trim($productCode))),
            'before_qty' => max(0, (int) ($updated['before_qty'] ?? 0)),
            'after_qty' => max(0, (int) ($updated['after_qty'] ?? 0)),
            'updated' => true,
        ];
    }

    /**
     * @return array{changed_by_name:string,changed_at:string,before_qty:int,after_qty:int,memo:string}|null
     */
    public function getLatestChangeByProductCode(string $productCode): ?array
    {
        $code = strtoupper(trim($productCode));
        if ($code === '') {
            return null;
        }

        $latest = StoreGnuboardStockChangeLog::query()
            ->with('user')
            ->where('product_code', $code)
            ->latest('id')
            ->first();

        if ($latest === null) {
            return null;
        }

        return [
            'changed_by_name' => trim((string) ($latest->user?->name ?? '')),
            'changed_at' => $latest->created_at !== null
                ? Carbon::parse((string) $latest->created_at)->format('Y-m-d H:i')
                : '',
            'before_qty' => max(0, (int) $latest->before_qty),
            'after_qty' => max(0, (int) $latest->after_qty),
            'memo' => trim((string) ($latest->memo ?? '')),
        ];
    }
}
