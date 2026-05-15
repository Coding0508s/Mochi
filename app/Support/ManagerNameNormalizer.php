<?php

namespace App\Support;

/**
 * 담당자 이름 표기 차이(공백/점/하이픈/언더스코어/쉼표)를 흡수하기 위한 공용 정규화 도구.
 *
 * - PHP 비교 키: normalize($value)
 * - SQL 비교 키: sqlColumnExpression($column) (PHP 결과와 정확히 같은 문자열을 만듭니다)
 *
 * 표기 예:
 * - "Peter Kim", "Peter.Kim", "Peter-Kim", "Peter_Kim", "Peter,Kim"
 * - 위 표기는 모두 "peterkim" 으로 정규화됩니다.
 */
final class ManagerNameNormalizer
{
    public static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $lower = mb_strtolower(trim($value));
        $normalized = preg_replace('/[\s.,_-]+/u', '', $lower);

        return is_string($normalized) ? $normalized : $lower;
    }

    /**
     * 주어진 컬럼명을 PHP normalize()와 같은 결과로 만드는 SQL 식을 반환합니다.
     * NOTE: MySQL/SQLite 모두 REPLACE/LOWER/COALESCE 를 지원하므로 raw 식으로 둡니다.
     */
    public static function sqlColumnExpression(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE({$column}, '')), ' ', ''), '.', ''), ',', ''), '_', ''), '-', '')";
    }
}
