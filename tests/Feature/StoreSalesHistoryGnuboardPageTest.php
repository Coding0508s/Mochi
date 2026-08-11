<?php

namespace Tests\Feature;

use App\Livewire\StoreSalesHistoryList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        Config::set('store.gnuboard.sales.order_memo_column', 'od_memo');
        Config::set('store.gnuboard.sales.order_member_id_column', 'mb_id');
        Config::set('store.gnuboard.sales.member_table', 'g5_member');
        Config::set('store.gnuboard.sales.member_id_column', 'mb_id');
        Config::set('store.gnuboard.sales.member_nickname_column', 'mb_nick');
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

        DB::connection('mysql_grapeseed_goods')->table('g5_member')->insert([
            'mb_id' => 'institution01',
            'mb_nick' => '행복학원',
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_order')->insert([
            ['od_id' => 'OD-OK', 'od_time' => now()->subHour()->format('Y-m-d H:i:s'), 'od_status' => '결제완료', 'od_settle_case' => '신용카드', 'od_name' => '홍길동', 'od_memo' => '기관 전화번호: 010-4232-4232', 'mb_id' => 'institution01'],
            ['od_id' => 'OD-CANCEL', 'od_time' => now()->subMinutes(30)->format('Y-m-d H:i:s'), 'od_status' => '취소', 'od_settle_case' => '신용카드', 'od_name' => '김취소', 'od_memo' => '취소 메모', 'mb_id' => 'institution01'],
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
            ->assertSee('Store 전체 판매내역')
            ->assertSee('그누보드 판매 상품')
            ->assertSee('주문번호')
            ->assertDontSee('OD-CANCEL');

        Http::assertNothingSent();

        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->assertSee('OD-OK')
            ->assertSee('3')
            ->assertSee('홍길동')
            ->assertSee('행복학원')
            ->assertSee('결제완료')
            ->assertSee('신용카드')
            ->assertSee('전하실 말씀')
            ->assertSee('기관 전화번호: 010-4232-4232')
            ->assertDontSee('OD-CANCEL');
    }

    public function test_sales_history_search_matches_institution_nickname(): void
    {
        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'ITEM-2',
            'it_model' => 'P-SEARCH',
            'it_name' => '검색용 상품',
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_member')->insert([
            'mb_id' => 'institution02',
            'mb_nick' => '별빛어린이집',
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_order')->insert([
            'od_id' => 'OD-SEARCH',
            'od_time' => now()->subHour()->format('Y-m-d H:i:s'),
            'od_status' => '결제완료',
            'od_settle_case' => '무통장',
            'od_name' => '이주문',
            'od_memo' => null,
            'mb_id' => 'institution02',
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_cart')->insert([
            'od_id' => 'OD-SEARCH',
            'it_id' => 'ITEM-2',
            'ct_qty' => 1,
            'it_name' => '검색용 상품',
            'ct_status' => '완료',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->set('search', '별빛어린이집')
            ->assertSee('OD-SEARCH')
            ->assertSee('별빛어린이집')
            ->assertDontSee('OD-OK');
    }

    public function test_exports_filtered_sales_history_to_excel(): void
    {
        DB::connection('mysql_grapeseed_goods')->table('g5_shop_item')->insert([
            'it_id' => 'ITEM-EXPORT',
            'it_model' => 'P-EXPORT',
            'it_name' => '엑셀보내기 상품',
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_member')->insert([
            'mb_id' => 'institution03',
            'mb_nick' => '엑셀학원',
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_order')->insert([
            ['od_id' => 'OD-EXPORT', 'od_time' => now()->subHour()->format('Y-m-d H:i:s'), 'od_status' => '결제완료', 'od_settle_case' => '신용카드', 'od_name' => '엑셀주문', 'od_memo' => '=HYPERLINK("http://evil.example")', 'mb_id' => 'institution03'],
            ['od_id' => 'OD-SKIP', 'od_time' => now()->subHour()->format('Y-m-d H:i:s'), 'od_status' => '취소', 'od_settle_case' => '신용카드', 'od_name' => '제외', 'od_memo' => null, 'mb_id' => 'institution03'],
        ]);

        DB::connection('mysql_grapeseed_goods')->table('g5_shop_cart')->insert([
            ['od_id' => 'OD-EXPORT', 'it_id' => 'ITEM-EXPORT', 'ct_qty' => 5, 'it_name' => '엑셀보내기 상품', 'ct_status' => '완료'],
            ['od_id' => 'OD-SKIP', 'it_id' => 'ITEM-EXPORT', 'ct_qty' => 1, 'it_name' => '엑셀보내기 상품', 'ct_status' => '완료'],
        ]);

        $user = User::factory()->create();
        $now = now();
        Carbon::setTestNow($now);

        $component = Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->set('dateStart', $now->copy()->subDays(7)->format('Y-m-d'))
            ->set('dateEnd', $now->format('Y-m-d'))
            ->call('exportToExcel')
            ->assertFileDownloaded('Store_판매내역_'.$now->format('Ymd_His').'.xlsx');

        $xlsxBinary = base64_decode((string) data_get($component->effects, 'download.content'), true);
        $this->assertNotFalse($xlsxBinary);
        $this->assertNotSame('', $xlsxBinary);

        $tempPath = tempnam(sys_get_temp_dir(), 'store-sales-export-').'.xlsx';
        file_put_contents($tempPath, $xlsxBinary);

        try {
            $sheet = IOFactory::load($tempPath)->getActiveSheet();

            $this->assertSame('전하실 말씀', $sheet->getCell('J1')->getValue());

            $memoCell = $sheet->getCell('J2');
            $this->assertSame(DataType::TYPE_STRING, $memoCell->getDataType());
            $this->assertSame('=HYPERLINK("http://evil.example")', $memoCell->getValue());
        } finally {
            @unlink($tempPath);
        }
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

        Schema::connection('mysql_grapeseed_goods')->create('g5_member', function (Blueprint $table) {
            $table->string('mb_id')->primary();
            $table->string('mb_nick')->nullable();
        });

        Schema::connection('mysql_grapeseed_goods')->create('g5_shop_order', function (Blueprint $table) {
            $table->string('od_id')->primary();
            $table->dateTime('od_time')->nullable();
            $table->string('od_status')->nullable();
            $table->string('od_settle_case')->nullable();
            $table->string('od_name')->nullable();
            $table->text('od_memo')->nullable();
            $table->string('mb_id')->nullable();
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
