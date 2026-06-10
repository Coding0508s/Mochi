<?php

namespace App\Support;

use App\Models\Teacher;
use Illuminate\Validation\ValidationException;

/**
 * 교사 지원 보고서 완료 처리 시 Teachers 테이블의 N차 완료 현황
 * (_1st_Support_Date / _1st_Support_Type 등)을 기록하는 공용 클래스.
 *
 * - $round 가 null 이면 "기록 안 함"으로 간주하고 아무 것도 하지 않는다.
 * - 이미 완료 날짜가 기록된 차수는 덮어쓰지 않고 ValidationException 을 던진다.
 * - Store 액션의 DB 트랜잭션 안에서 호출하는 것을 전제로 한다.
 */
final class TeacherSupportSlotSync
{
    public const ERROR_KEY = 'support_round';

    /** @var array<int, string> 차수 번호 → config 컬럼 키 접미사 */
    private const ROUND_SUFFIXES = [
        1 => '1st',
        2 => '2nd',
        3 => '3rd',
        4 => '4th',
    ];

    /**
     * 완료 처리된 보고서의 차수 기록을 Teachers 테이블에 반영한다.
     */
    public static function apply(Teacher $teacher, ?int $round, string $supportTypeLabel): void
    {
        if ($round === null) {
            return;
        }

        if (! isset(self::ROUND_SUFFIXES[$round])) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => '유효하지 않은 지원 차수입니다.',
            ]);
        }

        if (self::isRoundRecorded($teacher, $round)) {
            throw ValidationException::withMessages([
                self::ERROR_KEY => "{$round}차 완료 기록이 이미 있어 덮어쓸 수 없습니다. 다른 차수를 선택해 주세요.",
            ]);
        }

        $teacher->forceFill([
            self::completedDateColumn($round) => now(),
            self::completedTypeColumn($round) => $supportTypeLabel,
        ])->save();
    }

    /**
     * 아직 완료 기록이 없는 첫 번째 차수. 모두 기록되어 있으면 null.
     */
    public static function firstEmptyRound(Teacher $teacher): ?int
    {
        foreach (array_keys(self::ROUND_SUFFIXES) as $round) {
            if (! self::isRoundRecorded($teacher, $round)) {
                return $round;
            }
        }

        return null;
    }

    /**
     * 이미 완료 날짜가 기록된 차수 목록 (UI 에서 비활성 표시용).
     *
     * @return list<int>
     */
    public static function recordedRounds(Teacher $teacher): array
    {
        return array_values(array_filter(
            array_keys(self::ROUND_SUFFIXES),
            fn (int $round): bool => self::isRoundRecorded($teacher, $round),
        ));
    }

    public static function isRoundRecorded(Teacher $teacher, int $round): bool
    {
        return $teacher->getAttribute(self::completedDateColumn($round)) !== null;
    }

    public static function completedDateColumn(int $round): string
    {
        return self::column('completed_'.self::ROUND_SUFFIXES[$round]);
    }

    public static function completedTypeColumn(int $round): string
    {
        return self::column('type_'.self::ROUND_SUFFIXES[$round]);
    }

    private static function column(string $key): string
    {
        return (string) config("coach_teacher_support.columns.{$key}");
    }
}
