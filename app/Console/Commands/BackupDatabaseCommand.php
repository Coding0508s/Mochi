<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
                            {--force : production이 아니거나 비활성 상태여도 실행}';

    protected $description = '기본 DB 연결을 파일로 백업합니다. 운영에서는 주 1회 스케줄로 실행됩니다.';

    public function handle(DatabaseBackup $backup): int
    {
        $force = (bool) $this->option('force');
        $enabled = (bool) config('database_backup.enabled', false);

        if (! $force && ! $enabled) {
            $this->warn('DB_BACKUP_ENABLED=false 입니다. 운영 .env 에서 true 로 켠 뒤 다시 실행하세요. (--force 로 강제 가능)');

            return self::SUCCESS;
        }

        if (! $force && ! app()->isProduction()) {
            $this->error('운영(production) 환경에서만 백업합니다. 로컬/스테이징은 --force 가 필요합니다.');

            return self::FAILURE;
        }

        try {
            $result = $backup->run();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('백업 실패: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '백업 완료: disk=%s path=%s bytes=%d pruned=%d',
            $result['disk'],
            $result['path'],
            $result['bytes'],
            $result['pruned'],
        ));

        return self::SUCCESS;
    }
}
