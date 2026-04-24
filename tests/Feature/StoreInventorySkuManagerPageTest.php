<?php

namespace Tests\Feature;

use App\Livewire\StoreInventorySkuManager;
use App\Models\StoreInventorySku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StoreInventorySkuManagerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_store_inventory_sku_manager_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('store.inventory.skus.index'))
            ->assertOk()
            ->assertSee('스토어 품목 수정 (이미지 수정 및 품목 활성 비활성)')
            ->assertSee('품목명');
    }

    public function test_non_admin_cannot_open_store_inventory_sku_manager_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => false,
        ]);

        $this->actingAs($user)
            ->get(route('store.inventory.skus.index'))
            ->assertForbidden();
    }

    public function test_store_inventory_editor_can_open_store_inventory_sku_manager_page(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'can_manage_store_inventory' => true,
        ]);

        $this->actingAs($editor)
            ->get(route('store.inventory.skus.index'))
            ->assertOk()
            ->assertSee('스토어 품목 수정 (이미지 수정 및 품목 활성 비활성)');
    }

    public function test_admin_can_bulk_add_skus_from_comma_or_newline_text(): void
    {
        StoreInventorySku::query()->create([
            'prod_cd' => '00P227',
            'is_active' => true,
            'sort_order' => 0,
            'memo' => null,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(StoreInventorySkuManager::class)
            ->set('bulkProdCodes', "00P228,00P227\n00P211")
            ->call('bulkAddSkus');

        $this->assertDatabaseHas('store_inventory_skus', [
            'prod_cd' => '00P228',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('store_inventory_skus', [
            'prod_cd' => '00P211',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('store_inventory_skus', 3);
    }

    public function test_sku_manager_fills_product_name_from_ecount_when_gnuboard_has_no_match(): void
    {
        Config::set('store.data_source', 'ecount');
        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.zone', '');
        Config::set('store.ecount.session_id', 'session-test');
        Config::set('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        Config::set('store.ecount.product_basic_chunk_size', 20);
        Config::set('store.ecount.fetch_product_names', true);
        Config::set('store.timeout', 5);

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => 'ECOUNTONLY',
                            'PROD_DES' => '이카운트에서만 있는 품목명',
                        ],
                    ],
                ],
            ], 200),
        ]);

        StoreInventorySku::query()->create([
            'prod_cd' => 'ECOUNTONLY',
            'is_active' => true,
            'sort_order' => 0,
            'memo' => null,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(StoreInventorySkuManager::class)
            ->assertSee('ECOUNTONLY')
            ->assertSee('이카운트에서만 있는 품목명');
    }

    public function test_admin_can_delete_sku_row_from_platform_only(): void
    {
        $row = StoreInventorySku::query()->create([
            'prod_cd' => 'DELTEST',
            'is_active' => true,
            'sort_order' => 0,
            'memo' => null,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(StoreInventorySkuManager::class)
            ->call('deleteSku', $row->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('store_inventory_skus', ['id' => $row->id]);
    }

    public function test_admin_can_upload_sku_image_and_store_relative_path(): void
    {
        Storage::fake('public');

        $sku = StoreInventorySku::query()->create([
            'prod_cd' => 'IMG001',
            'is_active' => true,
            'sort_order' => 0,
            'memo' => null,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(StoreInventorySkuManager::class)
            ->set("rowImageFiles.{$sku->id}", UploadedFile::fake()->image('sku.png'))
            ->assertHasNoErrors();

        $sku->refresh();
        $this->assertNotNull($sku->image_url);
        $this->assertStringStartsWith('store-skus/', (string) $sku->image_url);
        Storage::disk('public')->assertExists((string) $sku->image_url);
    }

    public function test_remove_row_image_deletes_file_for_legacy_absolute_url_data(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store-skus/legacy.png', 'legacy-content');

        $sku = StoreInventorySku::query()->create([
            'prod_cd' => 'LEG001',
            'is_active' => true,
            'sort_order' => 0,
            'memo' => null,
            'image_url' => 'http://localhost/storage/store-skus/legacy.png',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(StoreInventorySkuManager::class)
            ->call('removeRowImage', $sku->id)
            ->assertHasNoErrors();

        $sku->refresh();
        $this->assertNull($sku->image_url);
        Storage::disk('public')->assertMissing('store-skus/legacy.png');
    }
}
