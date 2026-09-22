<?php

namespace App\Support;

final class ImportExecutionTime
{
    /**
     * 웹 요청의 기본 제한(보통 30초)을 엑셀 임포트용으로 늘린다.
     * PHPUnit은 max_execution_time=0(무제한)인데 set_time_limit(120)을
     * 호출하면 남은 스위트 전체가 120초로 잘린다.
     */
    public static function extendForLongImport(int $seconds = 120): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        set_time_limit($seconds);
    }
}
