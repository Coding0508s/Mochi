<?php

namespace Tests\Unit;

use App\Models\InstitutionExternalMapping;
use App\Models\StoreReturnRegistration;
use App\Support\StoreReturnSaleOrderPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

class StoreReturnSaleOrderPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.return_registration.ecount_enabled', false);
        Config::set('store.return_registration.sale_order_warehouse_code', 'GV01');
        Config::set('store.return_registration.sale_order_ref_des', '반품');
        Config::set('store.return_registration.sale_order_class_name_field', 'U_MEMO1');
        Config::set('store.return_registration.sale_order_shipping_address_field', 'ADD_LTXT_01_T');
    }

    public function test_builds_negative_qty_and_ref_des_per_line(): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_name' => '테스트 기관',
            'account_no' => 'X',
            'sk_code' => 'SK-PAYLOAD-1',
            'erp_institution_name' => '테스트 ERP 기관',
            'erp_account_no' => '1069626354',
            'portal_campus_id' => null,
        ]);

        $groupKey = 'group-payload-1';
        $returnedAt = '2026-07-08';

        $first = StoreReturnRegistration::factory()->create([
            'registration_group_key' => $groupKey,
            'returned_at' => $returnedAt,
            'institution_sk_code' => 'SK-PAYLOAD-1',
            'institution_name' => '테스트 기관',
            'item_name' => 'J11S-SSET-400',
            'quantity' => 2,
            'class_name' => '달빛처럼반',
            'ecount_remarks' => '반품 적요 1',
            'shipping_address' => '울산 북구 배송지',
        ]);

        $second = StoreReturnRegistration::factory()->create([
            'registration_group_key' => $groupKey,
            'returned_at' => $returnedAt,
            'institution_sk_code' => 'SK-PAYLOAD-1',
            'institution_name' => '테스트 기관',
            'item_name' => 'J12S-SSET-400',
            'quantity' => 1,
            'class_name' => '별빛반',
            'ecount_remarks' => '반품 적요 2',
            'shipping_address' => '울산 북구 배송지',
        ]);

        $items = Collection::make([$first, $second]);

        $payload = app(StoreReturnSaleOrderPayloadBuilder::class)->build($items);

        $this->assertCount(2, $payload['SaleOrderList']);

        $firstBulk = $payload['SaleOrderList'][0]['BulkDatas'];
        $this->assertSame('20260708', $firstBulk['IO_DATE']);
        $this->assertSame('1069626354', $firstBulk['CUST']);
        $this->assertSame('테스트 ERP 기관', $firstBulk['CUST_DES']);
        $this->assertSame('GV01', $firstBulk['WH_CD']);
        $this->assertSame('반품', $firstBulk['REF_DES']);
        $this->assertSame('', $firstBulk['EMP_CD']);
        $this->assertSame('J11S-SSET-400', $firstBulk['PROD_CD']);
        $this->assertSame('J11S-SSET-400', $firstBulk['PROD_DES']);
        $this->assertSame('-2', $firstBulk['QTY']);
        $this->assertSame('0', $firstBulk['PRICE']);
        $this->assertSame('0', $firstBulk['SUPPLY_AMT']);
        $this->assertSame('0', $firstBulk['VAT_AMT']);
        $this->assertSame('반품 적요 1', $firstBulk['REMARKS']);
        $this->assertSame('달빛처럼반', $firstBulk['U_MEMO1']);
        $this->assertSame('울산 북구 배송지', $firstBulk['ADD_LTXT_01_T']);

        $secondBulk = $payload['SaleOrderList'][1]['BulkDatas'];
        $this->assertSame('-1', $secondBulk['QTY']);
        $this->assertSame('J12S-SSET-400', $secondBulk['PROD_CD']);
        $this->assertSame('반품 적요 2', $secondBulk['REMARKS']);
        $this->assertSame('별빛반', $secondBulk['U_MEMO1']);
        $this->assertSame('울산 북구 배송지', $secondBulk['ADD_LTXT_01_T']);
    }

    public function test_throws_when_items_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('반품 등록 품목이 없습니다.');

        app(StoreReturnSaleOrderPayloadBuilder::class)->build(Collection::make());
    }

    public function test_throws_when_class_name_missing(): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_name' => '테스트 기관',
            'account_no' => 'X',
            'sk_code' => 'SK-PAYLOAD-2',
            'erp_institution_name' => '테스트 ERP 기관',
            'erp_account_no' => '1069626354',
            'portal_campus_id' => null,
        ]);

        $item = StoreReturnRegistration::factory()->create([
            'institution_sk_code' => 'SK-PAYLOAD-2',
            'item_name' => 'J11S-SSET-400',
            'class_name' => '',
            'ecount_remarks' => '적요 있음',
            'shipping_address' => '배송지',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class Name이 없습니다.');

        app(StoreReturnSaleOrderPayloadBuilder::class)->build(Collection::make([$item]));
    }

    public function test_throws_when_ecount_remarks_missing(): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_name' => '테스트 기관',
            'account_no' => 'X',
            'sk_code' => 'SK-PAYLOAD-3',
            'erp_institution_name' => '테스트 ERP 기관',
            'erp_account_no' => '1069626354',
            'portal_campus_id' => null,
        ]);

        $item = StoreReturnRegistration::factory()->create([
            'institution_sk_code' => 'SK-PAYLOAD-3',
            'item_name' => 'J11S-SSET-400',
            'class_name' => '달빛처럼반',
            'ecount_remarks' => null,
            'shipping_address' => '배송지',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('적요가 없습니다.');

        app(StoreReturnSaleOrderPayloadBuilder::class)->build(Collection::make([$item]));
    }

    public function test_throws_when_prod_cd_cannot_be_resolved(): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_name' => '테스트 기관',
            'account_no' => 'X',
            'sk_code' => 'SK-PAYLOAD-4',
            'erp_institution_name' => '테스트 ERP 기관',
            'erp_account_no' => '1069626354',
            'portal_campus_id' => null,
        ]);

        $item = StoreReturnRegistration::factory()->create([
            'institution_sk_code' => 'SK-PAYLOAD-4',
            'item_name' => '',
            'class_name' => '달빛처럼반',
            'ecount_remarks' => '적요',
            'shipping_address' => '배송지',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ecount 품목코드를 확인할 수 없습니다.');

        app(StoreReturnSaleOrderPayloadBuilder::class)->build(Collection::make([$item]));
    }
}
