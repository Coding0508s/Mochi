<?php

use App\Models\StoreInventorySku;
use App\Services\Store\EcountApiClient;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'ecount:session {--refresh : 캐시를 비우고 OAPILogin으로 세션을 다시 받습니다}',
    function () {
        /** @var EcountApiClient $client */
        $client = app(EcountApiClient::class);

        if ($this->option('refresh')) {
            $this->comment('세션·ZONE 캐시를 비우고 다시 발급합니다…');
        }

        $result = $client->tryOapiSession((bool) $this->option('refresh'));

        if ($result['ok']) {
            $this->info($result['detail']);
            $this->line('세션(앞 8자): '.substr($result['session_id'], 0, 8).'...');

            return Command::SUCCESS;
        }

        $this->error($result['detail']);
        $this->line('Zone 조회 URL: '.trim((string) config('store.ecount.zone_lookup_base_url', 'https://oapi.ecount.com')).' — 테스트망이면 sboapi 안내에 맞게 ECOUNT_ZONE_LOOKUP_BASE_URL 조정.');

        return Command::FAILURE;
    }
)->purpose('이카운트 세션 확인 및 OAPILogin 자동 발급');

Artisan::command(
    'store:gnuboard-db-check',
    function () {
        if (! (bool) config('store.gnuboard.enabled', true)) {
            $this->warn('STORE_GNUBOARD_ENABLED 가 false 입니다. 실 연동을 켜려면 .env 에서 true 로 두세요.');

            return Command::FAILURE;
        }

        $connection = (string) config('store.gnuboard.connection', 'mysql_grapeseed_goods');
        $cfg = config("database.connections.{$connection}");
        if (! is_array($cfg)) {
            $this->error("database.connections.{$connection} 설정이 없습니다.");

            return Command::FAILURE;
        }

        $this->line("연결 이름: <fg=cyan>{$connection}</>");
        $this->line(sprintf(
            'Host: %s | Port: %s | Database: %s',
            (string) ($cfg['host'] ?? ''),
            (string) ($cfg['port'] ?? ''),
            (string) ($cfg['database'] ?? ''),
        ));

        try {
            DB::connection($connection)->getPdo();
            DB::connection($connection)->select('select 1 as ok');
        } catch (Throwable $exception) {
            $this->error('접속 실패: '.$exception->getMessage());
            $this->comment('DB_GRAPESEED_GOODS_HOST / PORT / DATABASE / USERNAME / PASSWORD 값을 확인하세요. (미설정 시 DB_* 로 폴백)');

            return Command::FAILURE;
        }

        $this->info('MySQL 접속 및 select 1 성공.');

        $itemTable = (string) config('store.gnuboard.item_table', 'g5_shop_item');
        $orderTable = (string) config('store.gnuboard.sales.order_table', 'g5_shop_order');
        $cartTable = (string) config('store.gnuboard.sales.cart_table', 'g5_shop_cart');

        if (! Schema::connection($connection)->hasTable($itemTable)) {
            $this->error("상품 테이블을 찾을 수 없습니다: {$itemTable}");
            $this->comment('STORE_GNUBOARD_ITEM_TABLE 과 실제 DB 스키마를 맞추세요.');

            return Command::FAILURE;
        }
        $this->info("상품 테이블 확인: {$itemTable}");

        if (! Schema::connection($connection)->hasTable($orderTable)) {
            $this->warn("주문 테이블 미확인: {$orderTable} (판매내역을 gnuboard 로 쓸 때 필요)");
        } else {
            $this->info("주문 테이블 확인: {$orderTable}");
        }

        if (! Schema::connection($connection)->hasTable($cartTable)) {
            $this->warn("장바구니 테이블 미확인: {$cartTable} (판매내역을 gnuboard 로 쓸 때 필요)");
        } else {
            $this->info("장바구니 테이블 확인: {$cartTable}");
        }

        $this->newLine();
        $this->comment('다음 단계: php artisan config:clear 후 재고/판매 화면에서 실데이터를 확인하세요.');

        return Command::SUCCESS;
    }
)->purpose('실 그누보드 DB 연결과 핵심 테이블 존재 여부를 점검');

Artisan::command(
    'store:sku-image-normalize {--dry-run : 변경 예정 건수만 확인합니다}',
    function () {
        $rows = StoreInventorySku::query()
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->get(['id', 'prod_cd', 'image_url']);

        $updates = [];
        foreach ($rows as $row) {
            $before = (string) ($row->image_url ?? '');
            $after = StoreInventorySku::normalizeImagePath($before);
            if ($after === '' || $before === $after) {
                continue;
            }

            $updates[] = [
                'id' => (int) $row->id,
                'prod_cd' => (string) $row->prod_cd,
                'before' => $before,
                'after' => $after,
            ];
        }

        if ($updates === []) {
            $this->info('정규화 대상 이미지 경로가 없습니다.');

            return Command::SUCCESS;
        }

        $this->line('정규화 대상 건수: '.count($updates));
        foreach (array_slice($updates, 0, 10) as $preview) {
            $this->line(sprintf(
                '- #%d [%s] %s => %s',
                $preview['id'],
                $preview['prod_cd'],
                $preview['before'],
                $preview['after'],
            ));
        }
        if (count($updates) > 10) {
            $this->line('... (미리보기는 10건까지만 표시)');
        }

        if ((bool) $this->option('dry-run')) {
            $this->comment('dry-run 모드: DB는 변경하지 않았습니다.');

            return Command::SUCCESS;
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $row) {
                StoreInventorySku::query()
                    ->whereKey($row['id'])
                    ->update(['image_url' => $row['after']]);
            }
        });

        $this->info('정규화 완료: '.count($updates).'건');

        return Command::SUCCESS;
    }
)->purpose('store_inventory_skus.image_url 절대 URL을 상대 경로로 정규화');
