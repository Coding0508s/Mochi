<?php

namespace App\Console\Commands;

use App\Support\ExcelSerialDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormalizeTeacherPlanDates extends Command
{
    protected $signature = 'teachers:normalize-plan-dates
                            {--dry-run : 변경 없이 변환 대상만 출력}';

    protected $description = 'Teachers 계획 지원일의 엑셀 일련번호를 Y-m-d 날짜로 정규화합니다.';

    public function handle(): int
    {
        if (! Schema::hasTable('Teachers')) {
            $this->error('Teachers 테이블이 없습니다.');

            return self::FAILURE;
        }

        $columns = ExcelSerialDate::teacherPlanDateColumns();
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $scanned = 0;

        $this->info($dryRun ? '[dry-run] 변환 대상 조회 중...' : '계획 지원일 정규화 중...');

        DB::table('Teachers')
            ->orderBy('ID')
            ->chunkById(200, function ($rows) use ($columns, $dryRun, &$updated, &$scanned): void {
                foreach ($rows as $row) {
                    $scanned++;

                    foreach ($columns as $column) {
                        if (! property_exists($row, $column)) {
                            continue;
                        }

                        $raw = $row->{$column};

                        if (! ExcelSerialDate::isSerial($raw)) {
                            continue;
                        }

                        $normalized = ExcelSerialDate::toStorageString($raw);

                        if ($normalized === null) {
                            continue;
                        }

                        if ($dryRun) {
                            $this->line("ID={$row->ID} {$column}: {$raw} → {$normalized}");

                            continue;
                        }

                        DB::table('Teachers')
                            ->where('ID', $row->ID)
                            ->update([$column => $normalized]);

                        $updated++;
                    }
                }
            }, 'ID');

        $this->newLine();
        $this->info("스캔: {$scanned}건".($dryRun ? '' : ", 갱신: {$updated}건"));

        return self::SUCCESS;
    }
}
