<?php

namespace App\Support;

use App\Models\StoreReturnRegistration;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class StoreReturnSaleOrderPayloadBuilder
{
    public function __construct(
        private readonly StoreReturnEcountCustResolver $custResolver,
        private readonly StoreReturnEcountProductOptions $productOptions,
    ) {}

    /**
     * @param  Collection<int, StoreReturnRegistration>  $items
     * @return array{SaleOrderList: list<array{BulkDatas: array<string, string>}>}
     */
    public function build(Collection $items): array
    {
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('반품 등록 품목이 없습니다.');
        }

        /** @var StoreReturnRegistration $firstItem */
        $firstItem = $items->first();

        $cust = $this->custResolver->resolve(
            $firstItem->institution_sk_code,
            (string) $firstItem->institution_name,
        );

        $warehouseCode = (string) config('store.return_registration.sale_order_warehouse_code', 'GV01');
        $refDes = (string) config('store.return_registration.sale_order_ref_des', '반품');
        $classNameField = (string) config('store.return_registration.sale_order_class_name_field', 'U_MEMO1');
        $shippingAddressField = (string) config('store.return_registration.sale_order_shipping_address_field', 'ADD_LTXT_01_T');

        $saleOrderList = [];

        foreach ($items as $item) {
            $className = trim((string) ($item->class_name ?? ''));
            if ($className === '') {
                throw new InvalidArgumentException('Class Name이 없습니다.');
            }

            $remarks = trim((string) ($item->ecount_remarks ?? ''));
            if ($remarks === '') {
                throw new InvalidArgumentException('적요가 없습니다.');
            }

            $prodCd = $this->productOptions->selectionValueForStoredItemName((string) $item->item_name);
            if ($prodCd === '') {
                throw new InvalidArgumentException('Ecount 품목코드를 확인할 수 없습니다.');
            }

            $prodDes = $this->productOptions->displayNameForStoredItemName((string) $item->item_name);
            $ioDate = $item->returned_at?->format('Ymd') ?? '';
            $quantity = (int) $item->quantity;

            $bulkDatas = [
                'IO_DATE' => $ioDate,
                'CUST' => $cust['cust'],
                'CUST_DES' => $cust['cust_des'],
                'EMP_CD' => '',
                'WH_CD' => $warehouseCode,
                'REF_DES' => $refDes,
                'PROD_CD' => $prodCd,
                'PROD_DES' => $prodDes,
                'QTY' => (string) (-1 * $quantity),
                'PRICE' => '0',
                'SUPPLY_AMT' => '0',
                'VAT_AMT' => '0',
                'REMARKS' => $remarks,
                $classNameField => $className,
                $shippingAddressField => trim((string) ($item->shipping_address ?? '')),
            ];

            $saleOrderList[] = [
                'BulkDatas' => $bulkDatas,
            ];
        }

        return [
            'SaleOrderList' => $saleOrderList,
        ];
    }
}
