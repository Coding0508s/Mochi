<?php

namespace App\Console\Commands;

use App\Models\SharedSupply;
use App\Support\SharedSupplyCalendarSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

#[Signature('shared-supplies:backfill-calendar {--apply : 실제 동기화를 수행합니다 (기본은 dry-run)} {--chunk=500 : 청크 크기}')]
#[Description('기존 shared_supplies를 team_schedules(etc/team)로 일괄 동기화합니다.')]
class BackfillSharedSupplyCalendarSchedules extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('shared_supplies') || ! Schema::hasTable('team_schedules')) {
            $this->error('필수 테이블(shared_supplies, team_schedules)이 없습니다.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('team_schedules', 'source_type')
            || ! Schema::hasColumn('team_schedules', 'source_id')) {
            $this->error('team_schedules.source_type/source_id 컬럼이 없습니다. 마이그레이션 후 다시 실행하세요.');

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $apply = (bool) $this->option('apply');
        $sync = app(SharedSupplyCalendarSync::class);

        $stats = [
            'target' => 0,
            'processed' => 0,
        ];

        SharedSupply::query()
            ->with('item')
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $rows) use (&$stats, $apply, $sync): void {
                /** @var SharedSupply $row */
                foreach ($rows as $row) {
                    $stats['target']++;
                    if (! $apply) {
                        continue;
                    }

                    $sync->sync($row);
                    $stats['processed']++;
                }
            }, column: 'id');

        $this->table(
            ['항목', '건수'],
            [
                ['대상 shared_supplies', $stats['target']],
                ['실제 동기화', $stats['processed']],
            ]
        );

        if (! $apply) {
            $this->comment('dry-run 완료: 실제 생성/수정 없이 대상 건수만 확인했습니다. (--apply로 반영)');
        }

        return self::SUCCESS;
    }
}
