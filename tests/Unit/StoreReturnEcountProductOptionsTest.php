<?php

namespace Tests\Unit;

use App\Models\StoreReturnEcountProduct;
use App\Support\StoreReturnEcountProductOptions;
use App\Support\StoreReturnProductCodeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreReturnEcountProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.return_registration.ecount_enabled', true);
        Config::set('store.return_registration.ecount_product_codes', '');
        Config::set('store.return_registration.ecount_cache_ttl_seconds', 0);
        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.session_id', 'session-123');
        Config::set('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        Config::set('store.ecount.fetch_product_names', true);
    }

    public function test_returns_empty_options_when_no_return_product_codes_are_configured(): void
    {
        $options = app(StoreReturnEcountProductOptions::class)->options();

        $this->assertSame([], $options);
    }

    public function test_product_code_resolver_ignores_store_inventory_skus(): void
    {
        Config::set('store.ecount.product_code', '00P999');
        Config::set('store.return_registration.ecount_product_codes', '');

        $codes = app(StoreReturnProductCodeResolver::class)->resolveProductCodes();

        $this->assertSame([], $codes);
    }

    public function test_product_code_resolver_prefers_database_over_env_config(): void
    {
        Config::set('store.return_registration.ecount_product_codes', '00P999');

        StoreReturnEcountProduct::query()->create([
            'prod_cd' => '00P228',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $codes = app(StoreReturnProductCodeResolver::class)->resolveProductCodes();

        $this->assertSame(['00P228'], $codes);
    }

    public function test_builds_options_from_return_product_codes_and_ecount_product_names(): void
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

        Config::set('store.return_registration.ecount_product_codes', '00P228');

        $options = app(StoreReturnEcountProductOptions::class)->options();

        $this->assertCount(1, $options);
        $this->assertSame('00P228', $options[0]['value']);
        $this->assertSame('GrapeSEED Unit 4', $options[0]['label']);
        $this->assertSame(
            'GrapeSEED Unit 4',
            app(StoreReturnEcountProductOptions::class)->displayNameForProductCode('00P228'),
        );
    }

    public function test_uses_cached_product_name_from_database_when_available(): void
    {
        StoreReturnEcountProduct::query()->create([
            'prod_cd' => '00P050',
            'product_name' => 'LittleSEED Student Book 1',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $options = app(StoreReturnEcountProductOptions::class)->options();

        $this->assertCount(1, $options);
        $this->assertSame('00P050', $options[0]['value']);
        $this->assertSame('LittleSEED Student Book 1', $options[0]['label']);
    }

    public function test_display_name_for_stored_item_name_resolves_product_code_or_returns_label(): void
    {
        StoreReturnEcountProduct::query()->create([
            'prod_cd' => 'U01C-CM-400',
            'product_name' => 'GrapeSEED Unit 1 Class Material',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $options = app(StoreReturnEcountProductOptions::class);

        $this->assertSame(
            'GrapeSEED Unit 1 Class Material',
            $options->displayNameForStoredItemName('U01C-CM-400'),
        );
        $this->assertSame(
            'GrapeSEED Unit 1 Class Material',
            $options->displayNameForStoredItemName('GrapeSEED Unit 1 Class Material'),
        );
    }
}
