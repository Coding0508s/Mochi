<?php

use App\Models\BrochureRequest;
use App\Models\Invoice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 운송장 완료 건: 가장 최근 운송장 등록일이 3일 지난 신청 건 자동 삭제
Artisan::command('completed-requests:clean', function () {
    $cutoff = now()->subDays(3);
    $requestIds = Invoice::select('request_id')
        ->groupBy('request_id')
        ->havingRaw('MAX(created_at) <= ?', [$cutoff])
        ->pluck('request_id');
    $count = BrochureRequest::whereIn('id', $requestIds)->delete();
    $this->info("Deleted {$count} completed request(s) (invoices older than 3 days).");
})->purpose('Delete completed brochure requests whose latest invoice is older than 3 days');

Schedule::command('completed-requests:clean')->daily();
