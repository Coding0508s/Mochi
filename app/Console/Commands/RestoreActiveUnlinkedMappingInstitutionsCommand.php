<?php

namespace App\Console\Commands;

use App\Support\RestoreActiveUnlinkedMappingInstitutions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('institutions:restore-unlinked-mapping-active-exceptions {--apply : Customer_Type 해지 표시를 되돌립니다 (기본은 dry-run)}')]
#[Description('미연결 매핑 해지 처리 예외 11건의 운영 중 상태를 복원합니다.')]
class RestoreActiveUnlinkedMappingInstitutionsCommand extends Command
{
    public function handle(RestoreActiveUnlinkedMappingInstitutions $service): int
    {
        if (! Schema::hasTable('S_Account_Information')) {
            $this->error('필수 테이블(S_Account_Information)이 없습니다.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->info('미연결 매핑 해지 예외 기관 복원을 시작합니다.');
        $this->line('모드: '.($apply ? 'apply' : 'dry-run'));
        $this->line('대상 SK: '.implode(', ', RestoreActiveUnlinkedMappingInstitutions::EXCEPTION_SK_CODES));

        $result = $service->execute($apply);

        $this->newLine();
        $this->table(
            ['항목', '건수'],
            [
                ['대상', $result['target']],
                ['복원 예정/완료', $result['restored']],
                ['이미 운영 중', $result['already_active']],
                ['S_Account_Information 없음', $result['missing']],
            ]
        );

        if (! $apply) {
            $this->comment('dry-run 완료: 실제 변경 없이 대상 건수만 확인했습니다. (--apply로 반영)');
        } else {
            $this->info('예외 기관 복원이 완료되었습니다.');
        }

        return self::SUCCESS;
    }
}
