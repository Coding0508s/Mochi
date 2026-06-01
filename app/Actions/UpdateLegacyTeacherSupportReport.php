<?php

namespace App\Actions;

use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherSupportPayload;
use App\Support\LegacyTeacherSupportReportActionResolver;
use App\Support\LegacyTeacherSupportReportLinker;
use App\Support\LegacyTeacherSupportReportPersister;
use App\Support\TeacherSupportReportEditAuthorization;
use App\Support\TeacherSupportReportSupportRecordBuilder;
use App\Support\TeacherSupportReportSupportRecordSync;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class UpdateLegacyTeacherSupportReport
{
    /** @var array<string, class-string> */
    private const STORE_CLASSES = [
        'demo_lesson' => StoreTeacherDemoLessonSupportReport::class,
        'lva_fr' => StoreTeacherLvaFrSupportReport::class,
        'lva_fb' => StoreTeacherLvaFbSupportReport::class,
        'ls_onsite_lva' => StoreTeacherLsOnsiteLvaSupportReport::class,
        'onsite' => StoreTeacherOnsiteSupportReport::class,
        'pro_con' => StoreTeacherProConSupportReport::class,
        'open_class' => StoreTeacherOpenClassSupportReport::class,
        'unit21_plus' => StoreTeacherUnit21PlusSupportReport::class,
        'unit31_plus' => StoreTeacherUnit31PlusSupportReport::class,
    ];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(string $table, int $reportId, array $data, User $user): void
    {
        if (! Schema::hasTable($table)) {
            throw new InvalidArgumentException('수정할 교사 지원 보고서를 찾을 수 없습니다.');
        }

        $row = DB::table($table)->where('ID', $reportId)->first();
        if ($row === null) {
            throw new InvalidArgumentException('수정할 교사 지원 보고서를 찾을 수 없습니다.');
        }

        $action = LegacyTeacherSupportReportActionResolver::resolve($table, $row);
        if ($action === null) {
            throw new InvalidArgumentException('지원하지 않는 교사 지원 보고서 유형입니다.');
        }

        $teacherId = TeacherSupportReportEditAuthorization::legacyTeacherIdFromRow($table, $row);
        if ($teacherId === null) {
            throw new InvalidArgumentException('수정할 교사 지원 보고서를 찾을 수 없습니다.');
        }

        $teacher = Teacher::query()->findOrFail($teacherId);
        TeacherSupportReportEditAuthorization::ensureCanUpdateLegacy($user, $row, $teacher);

        $storeClass = self::STORE_CLASSES[$action] ?? null;
        if ($storeClass === null) {
            throw new InvalidArgumentException('지원하지 않는 교사 지원 보고서 유형입니다.');
        }

        /** @var object{validatedPayload: callable} $store */
        $store = app($storeClass);
        $validated = CoachTeacherSupportPayload::applyTrustedContext(
            $store->validatedPayload($data),
            $teacher,
        );

        $markCompleted = (bool) ($validated['mark_completed'] ?? false);
        $status = $markCompleted ? '완료' : '임시';

        DB::transaction(function () use ($table, $reportId, $action, $validated, $status, $markCompleted, $row): void {
            $existingSupportRecordId = LegacyTeacherSupportReportLinker::findExistingSupportRecordId($action, $validated);

            TeacherSupportReportSupportRecordSync::sync(
                $existingSupportRecordId,
                $markCompleted,
                TeacherSupportReportSupportRecordBuilder::build($action, $validated),
            );

            $updates = LegacyTeacherSupportReportPersister::build($table, $action, $validated, $status, $row);

            if ($updates !== []) {
                DB::table($table)->where('ID', $reportId)->update($updates);
            }
        });
    }
}
