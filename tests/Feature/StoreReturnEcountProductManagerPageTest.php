<?php

namespace Tests\Feature;

use App\Livewire\StoreReturnEcountProductManager;
use App\Models\StoreReturnEcountProduct;
use App\Models\User;
use App\Support\StoreReturnEcountProductOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StoreReturnEcountProductManagerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.session_id', 'session-123');
        Config::set('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        Config::set('store.ecount.fetch_product_names', true);
        Config::set('store.return_registration.ecount_cache_ttl_seconds', 0);
    }

    public function test_admin_can_open_return_product_manager_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('store.returns.products.index'))
            ->assertOk()
            ->assertSee('반품 등록 품목', false)
            ->assertSee('Store 재고와 별도 관리', false);
    }

    public function test_non_admin_cannot_open_return_product_manager_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => false,
        ]);

        $this->actingAs($user)
            ->get(route('store.returns.products.index'))
            ->assertForbidden();
    }

    public function test_store_inventory_editor_cannot_open_return_product_manager_page(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => true,
        ]);

        $this->actingAs($editor)
            ->get(route('store.returns.products.index'))
            ->assertForbidden();
    }

    public function test_admin_can_add_return_product_and_see_ecount_name(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => '00P228',
                            'PROD_DES' => 'GrapeSEED Unit 4',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(StoreReturnEcountProductManager::class)
            ->set('newProdCd', '00P228')
            ->set('newMemo', '반품 전용')
            ->call('addProduct')
            ->assertHasNoErrors()
            ->assertSee('GrapeSEED Unit 4', false);

        $this->assertDatabaseHas('store_return_ecount_products', [
            'prod_cd' => '00P228',
            'product_name' => 'GrapeSEED Unit 4',
            'memo' => '반품 전용',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_bulk_add_return_products(): void
    {
        StoreReturnEcountProduct::query()->create([
            'prod_cd' => '00P227',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(StoreReturnEcountProductManager::class)
            ->set('bulkProdCodes', "00P228,00P227\n00P211")
            ->call('bulkAddProducts')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('store_return_ecount_products', ['prod_cd' => '00P228']);
        $this->assertDatabaseHas('store_return_ecount_products', ['prod_cd' => '00P211']);
        $this->assertDatabaseCount('store_return_ecount_products', 3);
    }

    public function test_inactive_return_product_is_excluded_from_dropdown_options(): void
    {
        Config::set('store.return_registration.ecount_enabled', true);
        Config::set('store.ecount.fetch_product_names', true);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => '00P228',
                            'PROD_DES' => 'GrapeSEED Unit 4',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $product = StoreReturnEcountProduct::query()->create([
            'prod_cd' => '00P228',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(StoreReturnEcountProductManager::class)
            ->call('toggleActive', $product->id);

        $options = app(StoreReturnEcountProductOptions::class)->options();

        $this->assertSame([], $options);
    }
}
