<?php

namespace App\Support;

use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\UrgentSupportNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 기관 지원 보고서(S_SupportInfo_Account) 삭제 시
 * 연결된 MOCHI 교사 지원 보고서·현황 차수·알림을 함께 정리한다.
 */
final class SupportRecordCascadeDeleter
{
    public function delete(SupportRecord $record): void
    {
        DB::transaction(function () use ($record): void {
            $this->deleteLinkedTeacherReports((int) $record->ID);
            $this->deleteLinkedNotifications((int) $record->ID);

            $record->delete();
        });
    }

    private function deleteLinkedTeacherReports(int $supportRecordId): void
    {
        foreach (config('coach_teacher_legacy_support.mochi_report_tables', []) as $table => $typeLabel) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->where('support_record_id', $supportRecordId)
                ->get(['id', 'teacher_id', 'status', 'support_date']);

            foreach ($rows as $row) {
                if ((string) ($row->status ?? '') === '완료') {
                    $teacher = Teacher::query()->find((int) $row->teacher_id);
                    if ($teacher !== null) {
                        TeacherSupportSlotSync::clearMatchingCompletion(
                            $teacher,
                            (string) $typeLabel,
                            $row->support_date,
                        );
                    }
                }

                DB::table($table)->where('id', $row->id)->delete();
            }
        }
    }

    private function deleteLinkedNotifications(int $supportRecordId): void
    {
        if (! Schema::hasTable('urgent_support_notifications')) {
            return;
        }

        UrgentSupportNotification::query()
            ->where('support_record_id', $supportRecordId)
            ->delete();
    }
}
