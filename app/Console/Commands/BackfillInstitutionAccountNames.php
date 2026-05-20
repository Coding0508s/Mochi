<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('institutions:backfill-account-names {--apply : 실제 업데이트를 수행합니다 (기본은 dry-run)} {--chunk=500 : 청크 크기}')]
#[Description('S_Account_Information.Account_Name을 S_AccountName.AccountName으로 백필합니다.')]
class BackfillInstitutionAccountNames extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('S_AccountName') || ! Schema::hasTable('S_Account_Information')) {
            $this->error('필수 테이블(S_AccountName, S_Account_Information)이 없습니다.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $hasGsNumberTable = Schema::hasTable('S_GSNumber');

        $stats = [
            'total' => 0,
            'empty_name' => 0,
            'missing_institution' => 0,
            'unchanged' => 0,
            'would_update' => 0,
            'updated' => 0,
            'gs_updated' => 0,
        ];

        DB::table('S_Account_Information')
            ->select(['ID', 'SK_Code', 'Account_Name'])
            ->orderBy('ID')
            ->chunkById($chunkSize, function ($rows) use (&$stats, $apply, $hasGsNumberTable): void {
                foreach ($rows as $row) {
                    $stats['total']++;

                    $skCode = trim((string) ($row->SK_Code ?? ''));
                    $resolvedName = trim((string) ($row->Account_Name ?? ''));

                    if ($skCode === '' || $resolvedName === '') {
                        $stats['empty_name']++;

                        continue;
                    }

                    /** @var object|null $institution */
                    $institution = DB::table('S_AccountName')
                        ->where('SKcode', $skCode)
                        ->first(['ID', 'AccountName']);

                    if (! $institution) {
                        $stats['missing_institution']++;

                        continue;
                    }

                    $currentName = trim((string) ($institution->AccountName ?? ''));
                    if ($currentName === $resolvedName) {
                        $stats['unchanged']++;

                        continue;
                    }

                    $stats['would_update']++;
                    if (! $apply) {
                        continue;
                    }

                    DB::transaction(function () use ($skCode, $resolvedName, $hasGsNumberTable, &$stats): void {
                        DB::table('S_AccountName')
                            ->where('SKcode', $skCode)
                            ->update(['AccountName' => $resolvedName]);

                        if ($hasGsNumberTable) {
                            $stats['gs_updated'] += DB::table('S_GSNumber')
                                ->where('SKCode', $skCode)
                                ->update(['AccountName' => $resolvedName]);
                        }
                    });

                    $stats['updated']++;
                }
            }, column: 'ID');

        $this->table(
            ['항목', '건수'],
            [
                ['전체 처리 행', $stats['total']],
                ['기관명 없음/빈값', $stats['empty_name']],
                ['매칭 기관 없음', $stats['missing_institution']],
                ['이미 일치', $stats['unchanged']],
                ['변경 대상', $stats['would_update']],
                ['실제 변경', $stats['updated']],
                ['S_GSNumber 변경 행', $stats['gs_updated']],
            ]
        );

        if (! $apply) {
            $this->comment('dry-run 완료: 실제 변경 없이 대상 건수만 확인했습니다. (--apply로 반영)');
        }

        return self::SUCCESS;
    }
}
