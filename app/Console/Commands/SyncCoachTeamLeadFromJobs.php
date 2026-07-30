<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncCoachTeamLeadFromJobs extends Command
{
    protected $signature = 'users:sync-coach-team-lead-from-jobs {--revoke-ineligible : (deprecated) 조건 미충족 사용자의 is_coach_team_lead 를 false 로 내립니다}';

    protected $description = '(deprecated) users:sync-permissions-from-job-titles 를 사용하세요.';

    public function handle(): int
    {
        $this->warn('users:sync-coach-team-lead-from-jobs is deprecated. Use users:sync-permissions-from-job-titles instead.');

        return $this->call('users:sync-permissions-from-job-titles');
    }
}
