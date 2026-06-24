<?php

namespace App\Console\Commands;

use App\Support\MarkFormerlyUnlinkedInstitutionsAsTerminated as TerminatedMarkingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('institutions:mark-unlinked-mappings-terminated {--apply : S_Account_Information에 해지 상태를 실제 반영합니다 (기본은 dry-run)}')]
#[Description('외부 매핑 미연결로 신규 생성된 기관을 해지 기관(Customer_Type)으로 표시합니다.')]
class MarkFormerlyUnlinkedInstitutionsAsTerminatedCommand extends Command
{
    public function handle(TerminatedMarkingService $service): int
    {
        if (! Schema::hasTable('institution_external_mappings')
            || ! Schema::hasTable('S_AccountName')
            || ! Schema::hasTable('S_Account_Information')) {
            $this->error('필수 테이블(institution_external_mappings, S_AccountName, S_Account_Information)이 없습니다.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->info('미연결 매핑으로 생성된 기관의 해지 처리를 시작합니다.');
        $this->line('모드: '.($apply ? 'apply' : 'dry-run'));

        $result = $service->execute($apply);

        $this->newLine();
        $this->table(
            ['항목', '건수'],
            [
                ['대상 기관 수 (FGC_CreateDate null)', $result['target']],
                ['이미 해지', $result['already_terminated']],
                ['신규 S_Account_Information 생성 예정/완료', $apply ? $result['created'] : $result['would_create']],
                ['Customer_Type 갱신 예정/완료', $apply ? $result['updated'] : $result['would_update']],
            ]
        );

        if (! $apply) {
            $this->comment('dry-run 완료: 실제 변경 없이 대상 건수만 확인했습니다. (--apply로 반영)');
        } else {
            $this->info('해지 처리가 완료되었습니다.');
        }

        return self::SUCCESS;
    }
}
