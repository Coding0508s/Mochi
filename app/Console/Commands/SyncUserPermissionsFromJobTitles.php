<?php

namespace App\Console\Commands;

use App\Support\JobTitlePermissionSynchronizer;
use Illuminate\Console\Command;

class SyncUserPermissionsFromJobTitles extends Command
{
    protected $signature = 'users:sync-permissions-from-job-titles';

    protected $description = '직책 권한 매트릭스(job_title_permissions)를 users 기능 플래그에 동기화합니다. is_admin은 변경하지 않습니다.';

    public function handle(JobTitlePermissionSynchronizer $synchronizer): int
    {
        $this->warn('주의: 표가 비어 있거나 직책이 없으면 비관리자 7개 권한이 모두 꺼질 수 있습니다.');
        $this->warn('운영 절차: (1) migrate (2) Setup에서 표 설정 (3) 이 명령 수동 실행');

        $stats = $synchronizer->syncAll();

        $this->table(
            ['항목', '건수'],
            [
                ['동기화(변경됨)', $stats['synced']],
                ['관리자 건너뜀', $stats['skipped_admin']],
                ['직원 없음 건너뜀', $stats['skipped_no_employee']],
            ],
        );

        return self::SUCCESS;
    }
}
