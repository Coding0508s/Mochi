<?php

namespace App\Support;

use App\Models\SupportRecord;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * 기관 지원 내역(S_SupportInfo_Account) 완료/완료 취소 시
 * 연결된 MOCHI 교사 지원 보고서 status 와 Teachers N차 슬롯을 맞춘다.
 *
 * 교사 보고서 → 기관 지원 연동의 역방향. 삭제 cascade 와 대칭을 맞춘다.
 */
final class SupportRecordTeacherCompletionSync
{
    public function sync(SupportRecord $record, bool $completed): void
    {
        $supportRecordId = (int) $record->ID;
        if ($supportRecordId <= 0) {
            return;
        }

        DB::transaction(function () use ($supportRecordId, $completed): void {
            foreach (config('coach_teacher_legacy_support.mochi_report_tables', []) as $table => $typeLabel) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'support_record_id')) {
                    continue;
                }

                $rows = DB::table($table)
                    ->where('support_record_id', $supportRecordId)
                    ->get(['id', 'teacher_id', 'status', 'support_date']);

                foreach ($rows as $row) {
                    if ($completed) {
                        $this->markReportCompleted($table, (string) $typeLabel, $row);
                    } else {
                        $this->markReportInProgress($table, (string) $typeLabel, $row);
                    }
                }
            }
        });

        TeacherSupportCompletionDisplay::flushRequestCache();
        TeacherSupportNewTeacherDisplay::flushRequestCache();
    }

    /**
     * @param  object{id: mixed, teacher_id: mixed, status: mixed, support_date: mixed}  $row
     */
    private function markReportCompleted(string $table, string $typeLabel, object $row): void
    {
        $previousStatus = (string) ($row->status ?? '');
        if ($previousStatus === '완료') {
            return;
        }

        $payload = ['status' => '완료'];
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table($table)->where('id', $row->id)->update($payload);

        if (TeacherSupportNewTeacherDisplay::isNewTeacherSupportType($typeLabel)) {
            return;
        }

        $teacher = Teacher::query()->find((int) $row->teacher_id);
        if ($teacher === null) {
            return;
        }

        $round = TeacherSupportSlotSync::firstEmptyRound($teacher);
        if ($round === null) {
            return;
        }

        try {
            TeacherSupportSlotSync::apply(
                $teacher,
                $round,
                $typeLabel,
                $row->support_date ?? null,
            );
        } catch (ValidationException) {
            // 이미 동일 차수가 채워져 있으면 보고서 status 만 유지한다.
            // 교사 지원 현황은 완료 보고서 orphan fallback 으로도 보일 수 있다.
        }
    }

    /**
     * @param  object{id: mixed, teacher_id: mixed, status: mixed, support_date: mixed}  $row
     */
    private function markReportInProgress(string $table, string $typeLabel, object $row): void
    {
        $previousStatus = (string) ($row->status ?? '');
        if ($previousStatus !== '완료') {
            return;
        }

        if (! TeacherSupportNewTeacherDisplay::isNewTeacherSupportType($typeLabel)) {
            $teacher = Teacher::query()->find((int) $row->teacher_id);
            if ($teacher !== null) {
                TeacherSupportSlotSync::clearMatchingCompletion(
                    $teacher,
                    $typeLabel,
                    $row->support_date,
                );
            }
        }

        $payload = ['status' => '임시'];
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table($table)->where('id', $row->id)->update($payload);
    }
}
