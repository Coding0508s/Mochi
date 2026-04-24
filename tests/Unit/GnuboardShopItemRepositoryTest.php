<?php

namespace Tests\Unit;

use App\Repositories\GrapeSeed\GnuboardShopItemRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class GnuboardShopItemRepositoryTest extends TestCase
{
    private bool $sqliteGnuboardUsed = false;

    protected function tearDown(): void
    {
        if ($this->sqliteGnuboardUsed) {
            $this->restoreGnuboardConnection();
        }

        parent::tearDown();
    }

    public function test_normalize_product_code_strips_unicode_separator_padding(): void
    {
        $repo = new GnuboardShopItemRepository;
        $method = new ReflectionMethod(GnuboardShopItemRepository::class, 'normalizeProductCode');
        $method->setAccessible(true);

        $this->assertSame('00P222R', $method->invoke($repo, "00P222R\u{3000}"));
        $this->assertSame('00P222R', $method->invoke($repo, "\u{3000}00P222R"));
        $this->assertSame('00P222R', $method->invoke($repo, "  00P222R\u{00A0}\u{3000}  "));
    }

    public function test_get_stock_quantity_map_matches_it_model_with_trailing_ideographic_space(): void
    {
        $this->useSqliteGnuboardConnection();

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->integer('it_stock_qty')->default(0);
            $table->integer('it_noti_qty')->default(0);
        });

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'ROW-1',
            'it_model' => "00P222R\u{3000}",
            'it_stock_qty' => 17,
            'it_noti_qty' => 3,
        ]);

        $this->applyGnuboardConfig();

        $repo = new GnuboardShopItemRepository;
        $map = $repo->getStockQuantityMapByProductCodes(['00P222R']);

        $this->assertSame(['00P222R' => 17], $map);
    }

    public function test_update_stock_quantity_resolves_row_when_it_model_has_trailing_ideographic_space(): void
    {
        $this->useSqliteGnuboardConnection();

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->integer('it_stock_qty')->default(0);
            $table->integer('it_noti_qty')->default(0);
        });

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'ROW-2',
            'it_model' => "00P223R\u{3000}",
            'it_stock_qty' => 4,
            'it_noti_qty' => 0,
        ]);

        $this->applyGnuboardConfig();

        $repo = new GnuboardShopItemRepository;
        $result = $repo->updateStockQuantityByProductCode('00P223R', 9);

        $this->assertTrue($result['updated']);
        $this->assertSame(4, $result['before_qty']);
        $this->assertSame(9, $result['after_qty']);
        $this->assertSame(9, (int) DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->where('it_id', 'ROW-2')->value('it_stock_qty'));
    }

    public function test_get_product_name_map_matches_it_model_with_trailing_ideographic_space(): void
    {
        $this->useSqliteGnuboardConnection();

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->string('it_name')->nullable();
            $table->integer('it_stock_qty')->default(0);
        });

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'NM-1',
            'it_model' => "NM-CODE\u{3000}",
            'it_name' => '테스트 상품명',
            'it_stock_qty' => 0,
        ]);

        $this->applyGnuboardConfig();

        $repo = new GnuboardShopItemRepository;
        $map = $repo->getProductNameMapByProductCodes(['NM-CODE']);

        $this->assertSame(['NM-CODE' => '테스트 상품명'], $map);
    }

    public function test_get_category_path_map_resolves_three_level_names(): void
    {
        $this->useSqliteGnuboardConnection();

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_category', function (Blueprint $table) {
            $table->string('ca_id')->primary();
            $table->string('ca_name')->nullable();
        });

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->string('ca_id')->nullable();
            $table->string('ca_id2')->nullable();
            $table->string('ca_id3')->nullable();
            $table->integer('it_stock_qty')->default(0);
        });

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_category')->insert([
            ['ca_id' => '10', 'ca_name' => '교재'],
            ['ca_id' => '1010', 'ca_name' => '초등'],
            ['ca_id' => '101010', 'ca_name' => '리더스'],
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'IT-CAT-1',
            'it_model' => 'PROD-CAT-1',
            'ca_id' => '10',
            'ca_id2' => '1010',
            'ca_id3' => '101010',
            'it_stock_qty' => 0,
        ]);

        $this->applyGnuboardConfig();

        $repo = new GnuboardShopItemRepository;
        $map = $repo->getCategoryPathMapByProductCodes(['PROD-CAT-1']);

        $this->assertArrayHasKey('PROD-CAT-1', $map);
        $this->assertSame('교재 > 초등 > 리더스', $map['PROD-CAT-1']['category_path']);
        $this->assertSame('10|1010|101010', $map['PROD-CAT-1']['category_group_key']);
    }

    public function test_get_category_path_map_returns_uncategorized_when_category_ids_empty(): void
    {
        $this->useSqliteGnuboardConnection();

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_category', function (Blueprint $table) {
            $table->string('ca_id')->primary();
            $table->string('ca_name')->nullable();
        });

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->string('ca_id')->nullable();
            $table->string('ca_id2')->nullable();
            $table->string('ca_id3')->nullable();
            $table->integer('it_stock_qty')->default(0);
        });

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'IT-EMPTY-CAT',
            'it_model' => 'PROD-NO-CAT',
            'ca_id' => '',
            'ca_id2' => '',
            'ca_id3' => '',
            'it_stock_qty' => 0,
        ]);

        $this->applyGnuboardConfig();

        $repo = new GnuboardShopItemRepository;
        $map = $repo->getCategoryPathMapByProductCodes(['PROD-NO-CAT']);

        $this->assertArrayHasKey('PROD-NO-CAT', $map);
        $this->assertSame('미분류', $map['PROD-NO-CAT']['category_path']);
        $this->assertSame('미분류', $map['PROD-NO-CAT']['category_group_key']);
    }

    public function test_get_category_path_map_returns_empty_when_gnuboard_disabled(): void
    {
        $this->useSqliteGnuboardConnection();

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->string('ca_id')->nullable();
            $table->integer('it_stock_qty')->default(0);
        });

        Config::set('store.gnuboard.enabled', false);
        Config::set('store.gnuboard.connection', 'mysql_grapeseed_goods');
        Config::set('store.gnuboard.item_table', 'g5_shop_item');

        $repo = new GnuboardShopItemRepository;
        $map = $repo->getCategoryPathMapByProductCodes(['ANY']);

        $this->assertSame([], $map);
    }

    public function test_get_category_path_map_returns_empty_when_all_category_columns_invalid(): void
    {
        $this->useSqliteGnuboardConnection();

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->integer('it_stock_qty')->default(0);
        });

        Config::set('store.gnuboard.enabled', true);
        Config::set('store.gnuboard.connection', 'mysql_grapeseed_goods');
        Config::set('store.gnuboard.item_table', 'g5_shop_item');
        Config::set('store.gnuboard.product_code_column', 'it_model');
        Config::set('store.gnuboard.fallback_product_code_column', 'it_id');
        Config::set('store.gnuboard.item_category_l1_column', 'bad col');
        Config::set('store.gnuboard.item_category_l2_column', '');
        Config::set('store.gnuboard.item_category_l3_column', '');

        $repo = new GnuboardShopItemRepository;
        $map = $repo->getCategoryPathMapByProductCodes(['X']);

        $this->assertSame([], $map);
    }

    private function useSqliteGnuboardConnection(): void
    {
        $this->sqliteGnuboardUsed = true;

        Config::set('database.connections.mysql_grapeseed_goods', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('mysql_grapeseed_goods');
        DB::reconnect('mysql_grapeseed_goods');
    }

    private function restoreGnuboardConnection(): void
    {
        Config::offsetUnset('database.connections.mysql_grapeseed_goods');
        DB::purge('mysql_grapeseed_goods');
        $this->sqliteGnuboardUsed = false;
    }

    private function applyGnuboardConfig(): void
    {
        Config::set('store.gnuboard.enabled', true);
        Config::set('store.gnuboard.connection', 'mysql_grapeseed_goods');
        Config::set('store.gnuboard.item_table', 'g5_shop_item');
        Config::set('store.gnuboard.product_code_column', 'it_model');
        Config::set('store.gnuboard.fallback_product_code_column', 'it_id');
        Config::set('store.gnuboard.notify_quantity_column', 'it_noti_qty');
        Config::set('store.gnuboard.stock_quantity_column', 'it_stock_qty');
    }
}
