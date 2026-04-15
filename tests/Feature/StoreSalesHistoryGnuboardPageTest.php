<?php

namespace Tests\Feature;

use App\Livewire\StoreSalesHistoryList;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class StoreSalesHistoryGnuboardPageTest extends TestCase
{
    use RefreshDatabase;

    private bool $gnuboardSqliteConnected = false;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.data_source', 'ecount');
        Config::set('store.sales_history_source', 'gnuboard');
        Config::set('store.gnuboard.enabled', true);
        Config::set('store.gnuboard.connection', 'mysql_grapeseed_goods');
        Config::set('store.gnuboard.item_table', 'g5_shop_item');
        Config::set('store.gnuboard.product_code_column', 'it_model');
        Config::set('store.gnuboard.fallback_product_code_column', 'it_id');
        Config::set('store.gnuboard.item_name_column', 'it_name');
        Config::set('store.gnuboard.sales.order_table', 'g5_shop_order');
        Config::set('store.gnuboard.sales.cart_table', 'g5_shop_cart');
        Config::set('store.gnuboard.sales.order_id_column', 'od_id');
        Config::set('store.gnuboard.sales.order_datetime_column', 'od_time');
        Config::set('store.gnuboard.sales.order_status_column', 'od_status');
        Config::set('store.gnuboard.sales.cart_product_id_column', 'it_id');
        Config::set('store.gnuboard.sales.cart_quantity_column', 'ct_qty');
        Config::set('store.gnuboard.sales.cart_name_column', 'it_name');
        Config::set('store.gnuboard.sales.cart_status_column', 'ct_status');
        Config::set('store.gnuboard.sales.order_settle_case_column', 'od_settle_case');
        Config::set('store.gnuboard.sales.order_customer_name_column', 'od_name');
        Config::set('store.gnuboard.sales.excluded_order_statuses', ['취소']);
        Config::set('store.gnuboard.sales.excluded_cart_statuses', ['취소']);
        Config::set('store.gnuboard.sales.lookback_days', 30);
        Config::set('store.gnuboard.sales.max_histories_per_product', 5);
        Config::set('store.gnuboard.sales.max_rows_per_query', 1000);

        $this->useSqliteGnuboardConnection();
        $this->createGnuboardSalesTables();
    }

    protected function tearDown(): void
    {
        if ($this->gnuboardSqliteConnected) {
            Config::offsetUnset('database.connections.mysql_grapeseed_goods');
            DB::purge('mysql_grapeseed_goods');
            $this->gnuboardSqliteConnected = false;
        }

        parent::tearDown();
    }

    public function test_sales_history_page_reads_gnuboard_sales_rows_only(): void
    {
        DB::table('store_inventory_skus')->insert([
            'prod_cd' => 'P-GB',
            'is_active' => true,
            'sort_order' => 1,
            'memo' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'ITEM-1',
            'it_model' => "P-GB\u{3000}",
            'it_name' => '그누보드 판매 상품',
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_order')->insert([
            ['od_id' => 'OD-OK', 'od_time' => now()->subHour()->format('Y-m-d H:i:s'), 'od_status' => '결제완료', 'od_settle_case' => '신용카드', 'od_name' => '홍길동'],
            ['od_id' => 'OD-CANCEL', 'od_time' => now()->subMinutes(30)->format('Y-m-d H:i:s'), 'od_status' => '취소', 'od_settle_case' => '신용카드', 'od_name' => '김취소'],
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_cart')->insert([
            ['od_id' => 'OD-OK', 'it_id' => 'ITEM-1', 'ct_qty' => 3, 'it_name' => '카트명1', 'ct_status' => '완료'],
            ['od_id' => 'OD-CANCEL', 'it_id' => 'ITEM-1', 'ct_qty' => 9, 'it_name' => '카트명2', 'ct_status' => '완료'],
        ]);

        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.sales.index'))
            ->assertOk()
            ->assertSee('그누보드 주문 기준 최근 판매 내역')
            ->assertSee('그누보드 판매 상품')
            ->assertSee('내역 건수')
            ->assertDontSee('OD-CANCEL');

        Http::assertNothingSent();

        $rowKey = 'sale_'.md5('P-GB');
        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->call('selectProductRow', $rowKey)
            ->assertSet('selectedRowKey', $rowKey)
            ->assertSee('OD-OK')
            ->assertSee('-3')
            ->assertSee('홍길동')
            ->assertSee('주문자');
    }

    private function useSqliteGnuboardConnection(): void
    {
        Config::set('database.connections.mysql_grapeseed_goods', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('mysql_grapeseed_goods');
        DB::reconnect('mysql_grapeseed_goods');
        $this->gnuboardSqliteConnected = true;
    }

    private function createGnuboardSalesTables(): void
    {
        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_item', function (Blueprint $table) {
            $table->string('it_id')->primary();
            $table->string('it_model')->nullable();
            $table->string('it_name')->nullable();
        });

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_order', function (Blueprint $table) {
            $table->string('od_id')->primary();
            $table->dateTime('od_time')->nullable();
            $table->string('od_status')->nullable();
            $table->string('od_settle_case')->nullable();
            $table->string('od_name')->nullable();
        });

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_cart', function (Blueprint $table) {
            $table->id();
            $table->string('od_id');
            $table->string('it_id')->nullable();
            $table->integer('ct_qty')->default(0);
            $table->string('it_name')->nullable();
            $table->string('ct_status')->nullable();
        });
    }
}
