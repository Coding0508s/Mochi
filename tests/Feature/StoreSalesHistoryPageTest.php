<?php

namespace Tests\Feature;

use App\Livewire\StoreSalesHistoryList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StoreSalesHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.data_source', 'ecount');
        Config::set('store.sales_history_source', 'ecount');
        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.zone', '');
        Config::set('store.ecount.session_id', 'session-123');
        Config::set('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        Config::set('store.ecount.fetch_product_names', true);
        Config::set('store.ecount.sale_list_endpoint', '/OAPI/V2/Sale/GetListSale');
        Config::set('store.ecount.movement_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory');
        Config::set('store.ecount.movement_chunk_size', 20);
        Config::set('store.ecount.movement_lookback_days', 30);
        Config::set('store.timeout', 5);
    }

    public function test_sales_history_page_shows_latest_five_rows_per_product_from_sale_api(): void
    {
        $recentDate = now()->subDays(2)->format('Y-m-d');

        DB::table('store_inventory_skus')->insert([
            'prod_cd' => 'P-SALE',
            'is_active' => true,
            'sort_order' => 1,
            'memo' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SALE', 'PROD_DES' => '판매 테스트 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/Sale/GetListSale*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SALE', 'SALE_QTY' => '1', 'IO_DATE' => $recentDate, 'IO_TIME' => '10:01:00', 'SALE_NO' => 'SALE-001'],
                        ['PROD_CD' => 'P-SALE', 'SALE_QTY' => '2', 'IO_DATE' => $recentDate, 'IO_TIME' => '10:02:00', 'SALE_NO' => 'SALE-002'],
                        ['PROD_CD' => 'P-SALE', 'SALE_QTY' => '3', 'IO_DATE' => $recentDate, 'IO_TIME' => '10:03:00', 'SALE_NO' => 'SALE-003'],
                        ['PROD_CD' => 'P-SALE', 'SALE_QTY' => '4', 'IO_DATE' => $recentDate, 'IO_TIME' => '10:04:00', 'SALE_NO' => 'SALE-004'],
                        ['PROD_CD' => 'P-SALE', 'SALE_QTY' => '5', 'IO_DATE' => $recentDate, 'IO_TIME' => '10:05:00', 'SALE_NO' => 'SALE-005'],
                        ['PROD_CD' => 'P-SALE', 'SALE_QTY' => '6', 'IO_DATE' => $recentDate, 'IO_TIME' => '10:06:00', 'SALE_NO' => 'SALE-006'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $rowKey = 'sale_'.md5('P-SALE');
        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->assertSee('판매 테스트 상품')
            ->assertSee('내역 건수')
            ->call('selectProductRow', $rowKey)
            ->assertSet('selectedRowKey', $rowKey)
            ->assertSee('SALE-006')
            ->assertSee('SALE-005')
            ->assertSee('SALE-002')
            ->assertDontSee('SALE-001');
    }

    public function test_sales_history_page_uses_movement_fallback_when_sale_api_fails(): void
    {
        $recentDate = now()->subDays(1)->format('Y-m-d');

        DB::table('store_inventory_skus')->insert([
            'prod_cd' => 'P-FALLBACK',
            'is_active' => true,
            'sort_order' => 1,
            'memo' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-FALLBACK', 'PROD_DES' => 'Fallback 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/Sale/GetListSale*' => Http::response(['message' => 'fail'], 500),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-FALLBACK',
                            'OUT_QTY' => '3',
                            'IO_DATE' => $recentDate,
                            'IO_TIME' => '09:10:00',
                            'SLIP_NO' => 'MOVE-001',
                            'IO_GUBUN_NAME' => '주문출고',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->assertSee('Fallback 상품')
            ->call('selectProductRow', 'sale_'.md5('P-FALLBACK'))
            ->assertSee('MOVE-001')
            ->assertSee('-3');
    }

    public function test_sales_history_page_uses_movement_fallback_when_sale_api_is_empty(): void
    {
        Config::set('store.ecount.movement_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory?');
        $recentDate = now()->subDays(1)->format('Y-m-d');

        DB::table('store_inventory_skus')->insert([
            'prod_cd' => 'P-EMPTY',
            'is_active' => true,
            'sort_order' => 1,
            'memo' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-EMPTY', 'PROD_DES' => '빈응답 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/Sale/GetListSale*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-EMPTY',
                            'OUT_QTY' => '2',
                            'IO_DATE' => $recentDate,
                            'IO_TIME' => '07:10:00',
                            'SLIP_NO' => 'MOVE-EMPTY-1',
                            'IO_GUBUN_NAME' => '주문출고',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->assertSee('빈응답 상품')
            ->call('selectProductRow', 'sale_'.md5('P-EMPTY'))
            ->assertSee('MOVE-EMPTY-1')
            ->assertSee('-2');
    }

    public function test_sales_history_modal_rejects_date_range_wider_than_90_days(): void
    {
        $recentDate = now()->subDays(1)->format('Y-m-d');

        DB::table('store_inventory_skus')->insert([
            'prod_cd' => 'P-RANGE',
            'is_active' => true,
            'sort_order' => 1,
            'memo' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-RANGE', 'PROD_DES' => '기간테스트'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/Sale/GetListSale*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-RANGE', 'SALE_QTY' => '1', 'IO_DATE' => $recentDate, 'IO_TIME' => '10:00:00', 'SALE_NO' => 'S-RANGE-1'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response([
                'Status' => '200',
                'Data' => ['Result' => []],
            ], 200),
        ]);

        $user = User::factory()->create();
        $rowKey = 'sale_'.md5('P-RANGE');

        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->call('selectProductRow', $rowKey)
            ->set('modalDateStart', '2026-01-01')
            ->set('modalDateEnd', '2026-04-15')
            ->call('applyModalDateFilter')
            ->assertSee('90일 이하여야');
    }

    public function test_sales_history_page_hides_products_older_than_90_days_from_list(): void
    {
        $recentDate = now()->subDays(2)->format('Y-m-d');
        $oldDate = now()->subDays(91)->format('Y-m-d');

        DB::table('store_inventory_skus')->insert([
            [
                'prod_cd' => 'P-RECENT',
                'is_active' => true,
                'sort_order' => 1,
                'memo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prod_cd' => 'P-OLD',
                'is_active' => true,
                'sort_order' => 2,
                'memo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-RECENT', 'PROD_DES' => '최근판매 상품'],
                        ['PROD_CD' => 'P-OLD', 'PROD_DES' => '오래된판매 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/Sale/GetListSale*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-RECENT', 'SALE_QTY' => '2', 'IO_DATE' => $recentDate, 'IO_TIME' => '09:00:00', 'SALE_NO' => 'S-RECENT-1'],
                        ['PROD_CD' => 'P-OLD', 'SALE_QTY' => '3', 'IO_DATE' => $oldDate, 'IO_TIME' => '09:00:00', 'SALE_NO' => 'S-OLD-1'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->assertSee('최근판매 상품')
            ->assertDontSee('오래된판매 상품');
    }

    public function test_sales_history_page_shows_error_when_sale_and_fallback_fail(): void
    {
        DB::table('store_inventory_skus')->insert([
            'prod_cd' => 'P-ERR',
            'is_active' => true,
            'sort_order' => 1,
            'memo' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-ERR', 'PROD_DES' => '에러 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/Sale/GetListSale*' => Http::response(['message' => 'sale fail'], 500),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response(['message' => 'movement fail'], 500),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.sales.index'))
            ->assertOk()
            ->assertSee('판매내역 데이터를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.');
    }
}
