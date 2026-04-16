<?php

use App\Services\Store\EcountApiClient;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
