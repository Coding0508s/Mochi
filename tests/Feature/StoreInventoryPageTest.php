<?php

namespace Tests\Feature;

use App\Livewire\StoreInventoryList;
use App\Models\StoreGnuboardStockChangeLog;
use App\Models\StoreInventorySku;
use App\Models\User;
use App\Repositories\GrapeSeed\GnuboardShopItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class StoreInventoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.data_source', 'ecount');
        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.zone', '');
        Config::set('store.ecount.session_id', 'session-123');
        Config::set('store.ecount.inventory_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus');
        Config::set('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        Config::set('store.ecount.product_basic_chunk_size', 20);
        Config::set('store.ecount.fetch_product_names', true);
        Config::set('store.ecount.movement_endpoint', '');
        Config::set('store.ecount.sale_list_endpoint', '');
        Config::set('store.ecount.warehouse_code', 'W-01');
        Config::set('store.ecount.base_date', '20260414');
        Config::set('store.timeout', 5);
    }

    public function test_inventory_page_renders_rows_from_ecount_api(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => '1744078969',
                            'BAL_QTY' => '268.0000000000',
                        ],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => '1744078969',
                            'PROD_DES' => 'GrapeSEED Mr.Lineman 배지 (20개)',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('상품재고관리')
            ->assertSee('1744078969')
            ->assertSee('GrapeSEED Mr.Lineman 배지 (20개)');

        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), 'GetListInventoryBalanceStatus')) {
                return false;
            }

            if (! str_contains($request->url(), 'SESSION_ID=session-123')) {
                return false;
            }

            return $request['BASE_DATE'] === '20260414'
                && $request['WH_CD'] === 'W-01';
        });
    }

    public function test_inventory_page_shows_empty_state(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => ['Result' => []],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('재고 데이터가 없습니다.');
    }

    public function test_inventory_page_shows_error_message_on_api_failure(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response(['message' => 'error'], 500),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('재고 데이터를 불러오지 못했습니다.');
    }

    public function test_inventory_page_calculates_available_stock_from_warehouse_minus_pending(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-100',
                            'BAL_QTY' => '100.0000000000',
                        ],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-100',
                            'PROD_DES' => '테스트 상품',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('100');
    }

    public function test_inventory_page_reads_negative_bal_qty_as_zero_available_stock(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-100',
                            'BAL_QTY' => '-1.0000000000',
                        ],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-100',
                            'PROD_DES' => '테스트 상품',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('0');
    }

    public function test_inventory_page_uses_gnuboard_notify_quantity_map(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-NOTI', 'BAL_QTY' => '12'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-NOTI', 'PROD_DES' => '알림수량 테스트 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')
            ->once()
            ->andReturn(['P-NOTI' => 77]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')
            ->once()
            ->andReturn(['P-NOTI' => 33]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')
            ->once()
            ->andReturn([]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        Livewire::test(StoreInventoryList::class)
            ->assertSet('items.0.product_code', 'P-NOTI')
            ->assertSet('items.0.notify_quantity', 77)
            ->assertSet('items.0.actual_stock_quantity', 33)
            ->assertSee('알림수량');
    }

    public function test_inventory_page_converts_legacy_absolute_image_url_to_public_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store-skus/legacy-item.png', 'legacy-image');

        StoreInventorySku::query()->create([
            'prod_cd' => 'P-IMG',
            'is_active' => true,
            'sort_order' => 0,
            'memo' => null,
            'image_url' => 'http://localhost/storage/store-skus/legacy-item.png',
        ]);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-IMG', 'BAL_QTY' => '12'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-IMG', 'PROD_DES' => '이미지 레거시 상품'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->assertSet('items.0.product_code', 'P-IMG')
            ->assertSet('items.0.image_url', Storage::disk('public')->url('store-skus/legacy-item.png'));
    }

    public function test_open_actual_stock_modal_sets_item_and_latest_audit_info(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-AUDIT', 'BAL_QTY' => '15'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-AUDIT', 'PROD_DES' => '모달 정보 테스트 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn(['P-AUDIT' => 8]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->once()->andReturn([]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $admin = User::factory()->create(['is_admin' => true, 'name' => '관리자A']);

        StoreGnuboardStockChangeLog::query()->create([
            'product_code' => 'P-AUDIT',
            'before_qty' => 5,
            'after_qty' => 8,
            'changed_by' => $admin->id,
            'source' => 'store_inventory',
            'memo' => '초기 조정',
        ]);

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-AUDIT')
            ->assertSet('showActualStockModal', true)
            ->assertSet('actualStockModalProductCode', 'P-AUDIT')
            ->assertSet('actualStockModalProductName', '모달 정보 테스트 상품')
            ->assertSet('actualStockModalWarehouseStock', 15)
            ->assertSet('actualStockModalCurrentQty', 8)
            ->assertSet('actualStockModalLastChangedBy', '관리자A')
            ->assertCount('actualStockModalHistoryRows', 1);
    }

    public function test_open_actual_stock_modal_lists_only_store_inventory_source_logs(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SRC', 'BAL_QTY' => '20'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SRC', 'PROD_DES' => '소스 필터 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn(['P-SRC' => 3]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->once()->andReturn([]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $admin = User::factory()->create(['is_admin' => true, 'name' => '테스터']);

        StoreGnuboardStockChangeLog::query()->create([
            'product_code' => 'P-SRC',
            'before_qty' => 1,
            'after_qty' => 2,
            'changed_by' => $admin->id,
            'source' => 'legacy_import',
            'memo' => '다른 출처(목록에서 제외)',
        ]);

        StoreGnuboardStockChangeLog::query()->create([
            'product_code' => 'P-SRC',
            'before_qty' => 2,
            'after_qty' => 3,
            'changed_by' => $admin->id,
            'source' => 'store_inventory',
            'memo' => '재고 화면 저장',
        ]);

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-SRC')
            ->assertCount('actualStockModalHistoryRows', 1)
            ->assertSet('actualStockModalHistoryRows.0.memo', '재고 화면 저장')
            ->assertSet('actualStockModalLastChangedBy', '테스터');
    }

    public function test_open_actual_stock_modal_snapshot_includes_ecount_last_deduct_and_can_open_deduct_detail(): void
    {
        Config::set('store.ecount.movement_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory');
        Config::set('store.ecount.fetch_deduct_logs', true);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-ACT-DED', 'BAL_QTY' => '100'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-ACT-DED', 'PROD_DES' => '차감요약 테스트'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-ACT-DED',
                            'QTY' => '-5',
                            'PRINT_YN' => 'Y',
                            'PRINT_DATETIME' => '2026-04-20 10:00:00',
                            'SLIP_NO' => 'SLIP-ACT-99',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn(['P-ACT-DED' => 95]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->once()->andReturn([]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-ACT-DED')
            ->assertSet('actualStockModalHasEcountDeductSummary', true)
            ->assertSet('actualStockModalLastDeductQty', 5)
            ->assertSet('actualStockModalLastDeductRef', 'SLIP-ACT-99')
            ->call('switchToEcountDeductDetailFromActualStockModal')
            ->assertSet('showActualStockModal', false)
            ->assertSet('showDeductDetailModal', true)
            ->assertSet('selectedDeductItem.product_code', 'P-ACT-DED');
    }

    public function test_admin_can_save_actual_stock_from_modal_and_write_log_with_memo(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-UPD', 'BAL_QTY' => '10'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-UPD', 'PROD_DES' => '수정 테스트 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn(['P-UPD' => 7]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('updateStockQuantityByProductCode')->once()->with('P-UPD', 25)->andReturn([
            'product_code' => 'P-UPD',
            'before_qty' => 7,
            'after_qty' => 25,
            'updated' => true,
        ]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-UPD')
            ->set('actualStockModalNewQty', '25')
            ->set('actualStockModalMemo', '월말 실사 반영')
            ->call('saveActualStockFromModal')
            ->assertSet('items.0.actual_stock_quantity', 25)
            ->assertSet('saveError', null)
            ->assertSet('showActualStockModal', false)
            ->assertSee('스토어사이트 재고를 저장했습니다.');

        $this->assertDatabaseHas('store_gnuboard_stock_change_logs', [
            'product_code' => 'P-UPD',
            'before_qty' => 7,
            'after_qty' => 25,
            'changed_by' => $admin->id,
            'source' => 'store_inventory',
            'memo' => '월말 실사 반영',
        ]);
    }

    public function test_save_actual_stock_requires_memo(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-MEMO', 'BAL_QTY' => '10'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-MEMO', 'PROD_DES' => '메모 필수 테스트'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn(['P-MEMO' => 5]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->once()->andReturn([]);
        $mock->shouldNotReceive('updateStockQuantityByProductCode');
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-MEMO')
            ->set('actualStockModalNewQty', '12')
            ->set('actualStockModalMemo', '   ')
            ->call('saveActualStockFromModal')
            ->assertSet('showActualStockModal', true)
            ->assertSee('변경 사유를 입력해 주세요.');
    }

    public function test_save_actual_stock_rejects_when_quantity_unchanged(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SAME', 'BAL_QTY' => '10'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SAME', 'PROD_DES' => '동일 수량 테스트'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn(['P-SAME' => 9]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->once()->andReturn([]);
        $mock->shouldNotReceive('updateStockQuantityByProductCode');
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-SAME')
            ->set('actualStockModalNewQty', '9')
            ->set('actualStockModalMemo', '의미 없는 동일 저장 시도')
            ->call('saveActualStockFromModal')
            ->assertSet('showActualStockModal', true)
            ->assertSee('수량이 변경되지 않았습니다');
    }

    public function test_non_admin_cannot_open_or_save_actual_stock_modal(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-NOADMIN', 'BAL_QTY' => '10'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-NOADMIN', 'PROD_DES' => '권한 테스트 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->twice()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->twice()->andReturn(['P-NOADMIN' => 3]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->twice()->andReturn([]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-NOADMIN')
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->call('saveActualStockFromModal')
            ->assertForbidden();
    }

    public function test_inventory_page_rejects_invalid_actual_stock_input_from_modal(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-VALID', 'BAL_QTY' => '5'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-VALID', 'PROD_DES' => '검증 테스트 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->twice()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->twice()->andReturn(['P-VALID' => 1]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->twice()->andReturn([]);
        $mock->shouldNotReceive('updateStockQuantityByProductCode');
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-VALID')
            ->set('actualStockModalNewQty', '-3')
            ->call('saveActualStockFromModal')
            ->assertSet('saveError', '스토어사이트 재고는 0 이상이어야 합니다.');

        Livewire::actingAs($admin)
            ->test(StoreInventoryList::class)
            ->call('openActualStockModal', 'P-VALID')
            ->set('actualStockModalNewQty', 'abc')
            ->call('saveActualStockFromModal')
            ->assertSet('saveError', '스토어사이트 재고는 숫자로 입력해 주세요.');
    }

    public function test_inventory_request_joins_comma_separated_product_codes_with_ecount_delimiter(): void
    {
        Config::set('store.ecount.product_code', '00P228, 00P227');

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => '00P228', 'BAL_QTY' => '1'],
                        ['PROD_CD' => '00P227', 'BAL_QTY' => '2'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => '00P228', 'PROD_DES' => '상품A'],
                        ['PROD_CD' => '00P227', 'PROD_DES' => '상품B'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('store.inventory.index'))->assertOk();

        $joiner = "\u{222C}";
        Http::assertSent(function ($request) use ($joiner): bool {
            if (! str_contains($request->url(), 'GetListInventoryBalanceStatus')) {
                return false;
            }

            return ($request['PROD_CD'] ?? '') === '00P228'.$joiner.'00P227';
        });
    }

    public function test_inventory_page_is_read_only(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-100',
                            'BAL_QTY' => '10',
                        ],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-100',
                            'PROD_DES' => '상품1',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('조회 전용')
            ->assertDontSee('일괄수정')
            ->assertDontSee('재고수정');
    }

    public function test_inventory_page_shows_sku_manage_button_for_admin_or_store_inventory_editor(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => ['Result' => []],
            ], 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $inventoryEditor = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => true,
        ]);
        $normalUser = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('품목 추가');

        $this->actingAs($inventoryEditor)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('품목 추가');

        $this->actingAs($normalUser)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertDontSee('품목 추가');
    }

    public function test_inventory_page_renders_recent_deduct_fields_from_movement_api(): void
    {
        Config::set('store.ecount.movement_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory');
        Config::set('store.ecount.fetch_deduct_logs', true);
        Config::set('store.ecount.movement_chunk_size', 20);
        Config::set('store.ecount.movement_lookback_days', 30);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-100', 'BAL_QTY' => '15'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-100', 'PROD_DES' => '테스트 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-100',
                            'OUT_QTY' => '3',
                            'IO_DATE' => '2026-04-14',
                            'IO_TIME' => '13:30:00',
                            'IO_GUBUN_NAME' => '주문출고',
                            'SLIP_NO' => 'SLIP-100',
                            'REMARK' => '온라인 주문',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->assertSee('테스트 상품')
            ->assertSet('items.0.last_deduct_qty', 3);
    }

    public function test_inventory_page_renders_recent_deduct_fields_from_sale_list_api(): void
    {
        Config::set('store.ecount.movement_endpoint', '');
        Config::set('store.ecount.sale_list_endpoint', '/OAPI/V2/Sale/GetListSale');
        Config::set('store.ecount.fetch_deduct_logs', true);
        Config::set('store.ecount.movement_chunk_size', 20);
        Config::set('store.ecount.movement_lookback_days', 30);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SALE', 'BAL_QTY' => '10'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-SALE', 'PROD_DES' => '판매조회 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/Sale/GetListSale*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-SALE',
                            'SALE_QTY' => '4',
                            'IO_DATE' => '2026-04-13',
                            'IO_TIME' => '11:00:00',
                            'SALE_NO' => 'SALE-900',
                            'REMARK' => '스토어 판매',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->assertSee('판매조회 상품')
            ->assertSet('items.0.last_deduct_qty', 4);
    }

    public function test_inventory_deduct_detail_uses_printed_status_and_print_datetime(): void
    {
        Config::set('store.ecount.movement_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory');
        Config::set('store.ecount.fetch_deduct_logs', true);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-PRINT', 'BAL_QTY' => '12'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-PRINT', 'PROD_DES' => '인쇄출고 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'P-PRINT',
                            'QTY' => '-2',
                            'PRINT_YN' => 'Y',
                            'PRINT_DATETIME' => '2026-04-14 16:20:00',
                            'SLIP_NO' => 'SLIP-PRINT-1',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->call('openDeductDetail', 'P-PRINT')
            ->assertSet('showDeductDetailModal', true)
            ->assertSet('selectedDeductItem.last_deduct_type', '주문연동')
            ->assertSet('selectedDeductItem.last_deduct_at_display', function ($value): bool {
                return is_string($value)
                    && str_starts_with($value, '2026-04-14 ')
                    && str_ends_with($value, ':20');
            });
    }

    public function test_inventory_page_shows_deduct_fallback_when_movement_data_missing(): void
    {
        Config::set('store.ecount.movement_endpoint', '/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory');
        Config::set('store.ecount.fetch_deduct_logs', true);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-101', 'BAL_QTY' => '8'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-101', 'PROD_DES' => '차감없음 상품'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryMovementHistory*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->assertSee('차감없음 상품')
            ->assertSet('items.0.last_deduct_qty', null);
    }

    public function test_inventory_page_auto_logins_and_uses_cached_session_when_session_id_is_empty(): void
    {
        Config::set('store.ecount.session_id', '');
        Config::set('store.ecount.auto_login_when_empty_session', true);
        Config::set('store.ecount.com_code', 'COMPANY-01');
        Config::set('store.ecount.user_id', 'api-user');
        Config::set('store.ecount.api_cert_key', 'cert-key-123');
        Config::set('store.ecount.zone', 'BB');

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/OAPILogin' => Http::response([
                'Status' => '200',
                'Data' => [
                    'SESSION_ID' => 'session-auto-1',
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-AUTO', 'BAL_QTY' => '11'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-AUTO', 'PROD_DES' => '자동세션 상품'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('P-AUTO')
            ->assertSee('자동세션 상품');

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://oapi.ecount.com/OAPI/V2/OAPILogin'
                && $request['COM_CODE'] === 'COMPANY-01'
                && $request['USER_ID'] === 'api-user'
                && $request['API_CERT_KEY'] === 'cert-key-123'
                && $request['ZONE'] === 'BB';
        });

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'GetListInventoryBalanceStatus')
                && str_contains($request->url(), 'SESSION_ID=session-auto-1');
        });

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'GetBasicProductsList')
                && str_contains($request->url(), 'SESSION_ID=session-auto-1');
        });
    }

    public function test_inventory_page_auto_login_reads_session_id_from_data_result_row(): void
    {
        Config::set('store.ecount.session_id', '');
        Config::set('store.ecount.auto_login_when_empty_session', true);
        Config::set('store.ecount.com_code', 'COMPANY-01');
        Config::set('store.ecount.user_id', 'api-user');
        Config::set('store.ecount.api_cert_key', 'cert-key-123');
        Config::set('store.ecount.zone', 'BB');

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/OAPILogin' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['SESSION_ID' => 'session-from-result'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-RES', 'BAL_QTY' => '3'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-RES', 'PROD_DES' => 'Result행 세션'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('P-RES');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'SESSION_ID=session-from-result');
        });
    }

    public function test_inventory_page_auto_login_reads_session_id_from_datas_list(): void
    {
        Config::set('store.ecount.session_id', '');
        Config::set('store.ecount.auto_login_when_empty_session', true);
        Config::set('store.ecount.com_code', 'COMPANY-01');
        Config::set('store.ecount.user_id', 'api-user');
        Config::set('store.ecount.api_cert_key', 'cert-key-123');
        Config::set('store.ecount.zone', 'BB');

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/OAPILogin' => Http::response([
                'Status' => '200',
                'Datas' => [
                    ['SESSION_ID' => 'session-from-datas'],
                ],
                'Code' => '0000',
                'Message' => '',
                'LoginType' => 'oapi',
                'EXPIRE_DATE' => '',
                'NOTICE' => '',
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-DATAS', 'BAL_QTY' => '7'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-DATAS', 'PROD_DES' => 'Datas 형식 상품'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('P-DATAS');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'SESSION_ID=session-from-datas');
        });
    }

    public function test_inventory_page_chunks_inventory_api_when_active_product_codes_exceed_limit(): void
    {
        Config::set('store.ecount.product_code', '');
        Config::set('store.ecount.inventory_max_prod_cd', 20);

        for ($i = 1; $i <= 25; $i++) {
            DB::table('store_inventory_skus')->insert([
                'prod_cd' => sprintf('00P%03d', $i),
                'is_active' => true,
                'sort_order' => $i,
                'memo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => function ($request) {
                $codes = explode("\u{222C}", (string) ($request['PROD_CD'] ?? ''));

                return Http::response([
                    'Status' => '200',
                    'Data' => [
                        'Result' => array_map(fn (string $code): array => [
                            'PROD_CD' => $code,
                            'BAL_QTY' => '1',
                        ], array_filter($codes)),
                    ],
                ], 200);
            },
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => function ($request) {
                $codes = explode("\u{222C}", (string) ($request['PROD_CD'] ?? ''));

                return Http::response([
                    'Status' => '200',
                    'Data' => [
                        'Result' => array_map(fn (string $code): array => [
                            'PROD_CD' => $code,
                            'PROD_DES' => '품목-'.$code,
                        ], array_filter($codes)),
                    ],
                ], 200);
            },
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('00P001')
            ->assertSee('00P025');

        Http::assertSentCount(4);
    }

    /** @test */
    public function test_inventory_page_auto_login_reads_session_id_from_data_datas_list(): void
    {
        // 실제 이카운트 OAPILogin 응답 구조:
        // { "Status": "200", "Data": { "Code": "0000", "Datas": [{"SESSION_ID": "xxx"}], ... } }
        Config::set('store.ecount.session_id', '');
        Config::set('store.ecount.auto_login_when_empty_session', true);
        Config::set('store.ecount.com_code', 'COMPANY-01');
        Config::set('store.ecount.user_id', 'api-user');
        Config::set('store.ecount.api_cert_key', 'cert-key-123');
        Config::set('store.ecount.zone', 'BB');

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/OAPILogin' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Code' => '0000',
                    'Datas' => [
                        ['SESSION_ID' => 'session-from-data-datas'],
                    ],
                    'Message' => '',
                    'LoginType' => 'oapi',
                    'EXPIRE_DATE' => '',
                    'NOTICE' => '',
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-DATA-DATAS', 'BAL_QTY' => '3'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-DATA-DATAS', 'PROD_DES' => 'Data.Datas 형식 상품'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('P-DATA-DATAS');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'SESSION_ID=session-from-data-datas');
        });
    }

    public function test_inventory_page_renders_category_filter_dropdown(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-CAT-A', 'BAL_QTY' => '5'],
                        ['PROD_CD' => 'P-CAT-B', 'BAL_QTY' => '6'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-CAT-A', 'PROD_DES' => '카테고리 A 상품'],
                        ['PROD_CD' => 'P-CAT-B', 'PROD_DES' => '카테고리 B 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')
            ->once()
            ->andReturn([
                'P-CAT-A' => [
                    'category_l1' => '교재',
                    'category_l2' => '초등',
                    'category_l3' => '',
                    'category_path' => '교재 > 초등',
                    'category_group_key' => '10|1010',
                ],
                'P-CAT-B' => [
                    'category_l1' => '굿즈',
                    'category_l2' => '',
                    'category_l3' => '',
                    'category_path' => '굿즈',
                    'category_group_key' => '20',
                ],
            ]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.inventory.index'))
            ->assertOk()
            ->assertSee('카테고리')
            ->assertSee('교재 > 초등')
            ->assertSee('굿즈');
    }

    public function test_inventory_category_filter_limits_rows(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-X1', 'BAL_QTY' => '1'],
                        ['PROD_CD' => 'P-X2', 'BAL_QTY' => '2'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-X1', 'PROD_DES' => '알파'],
                        ['PROD_CD' => 'P-X2', 'PROD_DES' => '베타'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')
            ->once()
            ->andReturn([
                'P-X1' => [
                    'category_l1' => 'A',
                    'category_l2' => '',
                    'category_l3' => '',
                    'category_path' => 'A',
                    'category_group_key' => 'a1',
                ],
                'P-X2' => [
                    'category_l1' => 'B',
                    'category_l2' => '',
                    'category_l3' => '',
                    'category_path' => 'B',
                    'category_group_key' => 'b1',
                ],
            ]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->assertSet('totalItems', 2)
            ->set('categoryFilter', 'B')
            ->assertSet('totalItems', 1)
            ->assertSee('베타');
    }

    public function test_inventory_search_and_category_filter_combined(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-Y1', 'BAL_QTY' => '1'],
                        ['PROD_CD' => 'P-Y2', 'BAL_QTY' => '2'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-Y1', 'PROD_DES' => '공통 접두어 알파'],
                        ['PROD_CD' => 'P-Y2', 'PROD_DES' => '공통 접두어 베타'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')
            ->once()
            ->andReturn([
                'P-Y1' => [
                    'category_l1' => '같은카테',
                    'category_l2' => '',
                    'category_l3' => '',
                    'category_path' => '같은카테',
                    'category_group_key' => 'g1',
                ],
                'P-Y2' => [
                    'category_l1' => '같은카테',
                    'category_l2' => '',
                    'category_l3' => '',
                    'category_path' => '같은카테',
                    'category_group_key' => 'g1',
                ],
            ]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->set('categoryFilter', '같은카테')
            ->set('search', '베타')
            ->assertSet('totalItems', 1)
            ->assertSee('베타');
    }

    public function test_inventory_category_path_defaults_to_uncategorized_when_map_missing(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBalance/GetListInventoryBalanceStatus*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-UN', 'BAL_QTY' => '1'],
                    ],
                ],
            ], 200),
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        ['PROD_CD' => 'P-UN', 'PROD_DES' => '미분류 상품'],
                    ],
                ],
            ], 200),
        ]);

        $mock = Mockery::mock(GnuboardShopItemRepository::class);
        $mock->shouldReceive('getNotifyQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getStockQuantityMapByProductCodes')->once()->andReturn([]);
        $mock->shouldReceive('getCategoryPathMapByProductCodes')->once()->andReturn([]);
        $this->app->instance(GnuboardShopItemRepository::class, $mock);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreInventoryList::class)
            ->assertSet('items.0.category_path', '미분류')
            ->assertSee('미분류');
    }
}
