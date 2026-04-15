<?php

namespace Tests\Feature;

use App\Livewire\StoreInventorySkuManager;
use App\Models\StoreInventorySku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('스토어 재고 연동 품목')
            ->assertSee('품목명');
    }

    public function test_non_admin_cannot_open_store_inventory_sku_manager_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('store.inventory.skus.index'))
            ->assertForbidden();
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
            ->set('bulkSortOrderStart', 10)
            ->call('bulkAddSkus');

        $this->assertDatabaseHas('store_inventory_skus', [
            'prod_cd' => '00P228',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('store_inventory_skus', [
            'prod_cd' => '00P211',
            'sort_order' => 11,
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('store_inventory_skus', 3);
    }
}
