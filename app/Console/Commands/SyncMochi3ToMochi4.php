<?php

namespace App\Console\Commands;

use App\Support\Mochi3ToMochi4Sync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncMochi3ToMochi4 extends Command
{
    protected $signature = 'db:sync-mochi3-to-mochi4
                            {--dry-run : 실제 UPDATE 없이 변경 예정 건수만 표시}
                            {--migrate : 실행 전 Teachers 등 누락 컬럼 마이그레이션 적용}
                            {--force : DB_DATABASE 가 Mochi4 가 아니어도 실행}
                            {--yes : 확인 없이 실행}';

    protected $description = 'Mochi3(운영)의 변경분을 현재 연결 DB(Mochi4) 레거시 테이블에 반영합니다. Mochi3·users·g5_* 는 수정하지 않습니다.';

    public function handle(Mochi3ToMochi4Sync $sync): int
    {
        $target = $sync->targetDatabaseName();

        if ($target !== 'Mochi4' && ! $this->option('force')) {
            $this->error("현재 DB_DATABASE={$target} 입니다. Mochi4 가 아니면 --force 없이 중단합니다.");

            return self::FAILURE;
        }

        $this->info('소스: '.Mochi3ToMochi4Sync::SOURCE_DATABASE.' → 대상: '.$target);
        $this->line('대상 테이블: '.implode(', ', $sync->syncableTables()));

        if ($this->option('migrate')) {
            $this->info('마이그레이션 적용 중…');
            Artisan::call('migrate', ['--force' => true]);
            $this->line(trim(Artisan::output()));
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN: DB 는 변경하지 않습니다.');
        } elseif (! $this->option('yes') && ! $this->confirm('Mochi4 데이터를 Mochi3 변경분으로 갱신합니다. 계속할까요?', true)) {
            $this->info('취소했습니다.');

            return self::SUCCESS;
        }

        try {
            $results = $sync->run($dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                $result['table'],
                $result['would_update'],
                $dryRun ? '-' : $result['updated'],
                $result['would_insert'],
                $dryRun ? '-' : $result['inserted'],
                $result['skipped_reason'] ?? 'OK',
            ];
        }

        $this->table(
            ['테이블', 'UPDATE 예정', $dryRun ? 'UPDATE' : 'UPDATE됨', 'INSERT 예정', $dryRun ? 'INSERT' : 'INSERT됨', '비고'],
            $rows,
        );

        $skipped = array_filter($results, static fn (array $r): bool => $r['skipped_reason'] !== null);
        if ($skipped !== []) {
            $this->newLine();
            $this->warn('일부 테이블은 건너뛰었습니다. Teachers 는 `php artisan migrate` 후 다시 실행하세요.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('미리보기 완료. 반영하려면 --dry-run 없이 다시 실행하세요.');
        } else {
            $this->info('동기화 완료.');
        }

        return self::SUCCESS;
    }
}
