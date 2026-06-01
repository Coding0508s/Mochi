<?php

namespace App\Actions;

use App\Models\Teacher;
use App\Models\TeacherDemoLessonSupportReport;
use App\Models\TeacherLittleseedConSupportReport;
use App\Models\TeacherLsOnsiteLvaSupportReport;
use App\Models\TeacherLvaFbSupportReport;
use App\Models\TeacherLvaFrSupportReport;
use App\Models\TeacherOnsiteSupportReport;
use App\Models\TeacherOpenClassSupportReport;
use App\Models\TeacherProConSupportReport;
use App\Models\TeacherUnit21PlusSupportReport;
use App\Models\TeacherUnit31PlusSupportReport;
use App\Models\User;
use App\Support\CoachTeacherSupportPayload;
use App\Support\TeacherSupportReportEditAuthorization;
use App\Support\TeacherSupportReportSupportRecordBuilder;
use App\Support\TeacherSupportReportSupportRecordSync;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateTeacherSupportReport
{
    /** @var array<string, class-string> */
    private const STORE_CLASSES = [
        'demo_lesson' => StoreTeacherDemoLessonSupportReport::class,
        'lva_fr' => StoreTeacherLvaFrSupportReport::class,
        'lva_fb' => StoreTeacherLvaFbSupportReport::class,
        'ls_onsite_lva' => StoreTeacherLsOnsiteLvaSupportReport::class,
        'littleseed_con' => StoreTeacherLittleseedConSupportReport::class,
        'onsite' => StoreTeacherOnsiteSupportReport::class,
        'pro_con' => StoreTeacherProConSupportReport::class,
        'open_class' => StoreTeacherOpenClassSupportReport::class,
        'unit21_plus' => StoreTeacherUnit21PlusSupportReport::class,
        'unit31_plus' => StoreTeacherUnit31PlusSupportReport::class,
    ];

    /** @var array<string, class-string<Model>> */
    private const MODEL_CLASSES = [
        'teacher_demo_lesson_support_reports' => TeacherDemoLessonSupportReport::class,
        'teacher_lva_fr_support_reports' => TeacherLvaFrSupportReport::class,
        'teacher_lva_fb_support_reports' => TeacherLvaFbSupportReport::class,
        'teacher_ls_onsite_lva_support_reports' => TeacherLsOnsiteLvaSupportReport::class,
        'teacher_littleseed_con_support_reports' => TeacherLittleseedConSupportReport::class,
        'teacher_onsite_support_reports' => TeacherOnsiteSupportReport::class,
        'teacher_pro_con_support_reports' => TeacherProConSupportReport::class,
        'teacher_open_class_support_reports' => TeacherOpenClassSupportReport::class,
        'teacher_unit21_plus_support_reports' => TeacherUnit21PlusSupportReport::class,
        'teacher_unit31_plus_support_reports' => TeacherUnit31PlusSupportReport::class,
    ];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(string $table, int $reportId, array $data, User $user): Model
    {
        $action = config("coach_teacher_support_history_modal.mochi_table_actions.{$table}");
        if (! is_string($action) || $action === '') {
            throw new InvalidArgumentException('지원하지 않는 교사 지원 보고서 유형입니다.');
        }

        $modelClass = self::MODEL_CLASSES[$table] ?? null;
        if ($modelClass === null) {
            throw new InvalidArgumentException('지원하지 않는 교사 지원 보고서 테이블입니다.');
        }

        /** @var Model|null $report */
        $report = $modelClass::query()->find($reportId);
        if ($report === null) {
            throw new InvalidArgumentException('수정할 교사 지원 보고서를 찾을 수 없습니다.');
        }

        $teacher = Teacher::query()->findOrFail((int) $report->getAttribute('teacher_id'));
        TeacherSupportReportEditAuthorization::ensureCanUpdate($user, $report, $teacher);

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

        return DB::transaction(function () use ($report, $action, $validated, $status, $markCompleted): Model {
            $existingSupportRecordId = filled($report->getAttribute('support_record_id'))
                ? (int) $report->getAttribute('support_record_id')
                : null;

            $supportRecordId = TeacherSupportReportSupportRecordSync::sync(
                $existingSupportRecordId,
                $markCompleted,
                TeacherSupportReportSupportRecordBuilder::build($action, $validated),
            );

            $attributeKeys = array_merge(
                array_diff(array_keys($validated), ['mark_completed']),
                ['status', 'support_record_id'],
            );

            $report->update(collect($this->reportAttributes($action, $validated, $status, $supportRecordId))
                ->only($attributeKeys)
                ->only($report->getFillable())
                ->all());

            return $report->fresh() ?? $report;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function reportAttributes(string $action, array $validated, string $status, ?int $supportRecordId): array
    {
        $base = [
            'sk_code' => $validated['sk_code'],
            'coach_name' => $validated['coach_name'],
            'institution_name' => $validated['institution_name'],
            'teacher_name' => $validated['teacher_name'],
            'support_date' => $validated['support_date'],
            'status' => $status,
            'support_record_id' => $supportRecordId,
        ];

        return match ($action) {
            'demo_lesson' => array_merge($base, [
                'progress_unit' => $validated['progress_unit'] ?? null,
                'progress_lesson' => $validated['progress_lesson'] ?? null,
                'other_notes' => $validated['other_notes'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'verbal_tools' => $validated['verbal_tools'] ?? [],
                'language_arts_tools' => $validated['language_arts_tools'] ?? [],
                'comments_primary' => $validated['comments_primary'] ?? null,
                'comments_secondary' => $validated['comments_secondary'] ?? null,
                'evaluations' => $validated['evaluations'] ?? [],
                'overall_comments' => $validated['overall_comments'] ?? null,
            ]),
            'lva_fr', 'lva_fb' => array_merge($base, [
                'observe_unit' => $validated['observe_unit'] ?? null,
                'observe_lesson' => $validated['observe_lesson'] ?? null,
                'observe_class' => $validated['observe_class'] ?? null,
                'observe_age' => $validated['observe_age'] ?? null,
                'teacher_experience' => $validated['teacher_experience'] ?? null,
                'session_number' => $validated['session_number'] ?? null,
                'semester_label' => $validated['semester_label'] ?? null,
                'interview_date' => $validated['interview_date'] ?? null,
                'interview_time' => $validated['interview_time'] ?? null,
                'method' => $validated['method'] ?? null,
                'other_notes' => $validated['other_notes'] ?? null,
                'video_length_minutes' => $validated['video_length_minutes'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'strength_areas' => $validated['strength_areas'] ?? [],
                'growth_areas' => $validated['growth_areas'] ?? [],
            ]),
            'onsite' => array_merge($base, [
                'observe_unit' => $validated['observe_unit'] ?? null,
                'observe_lesson' => $validated['observe_lesson'] ?? null,
                'observe_summary_extra' => $validated['observe_summary_extra'] ?? null,
                'observe_class' => $validated['observe_class'] ?? null,
                'observe_age' => $validated['observe_age'] ?? null,
                'teacher_experience' => $validated['teacher_experience'] ?? null,
                'session_number' => $validated['session_number'] ?? null,
                'semester_label' => $validated['semester_label'] ?? null,
                'interview_date' => $validated['interview_date'] ?? null,
                'interview_time' => $validated['interview_time'] ?? null,
                'method' => $validated['method'] ?? null,
                'other_notes' => $validated['other_notes'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'strength_areas' => $validated['strength_areas'] ?? [],
                'growth_areas' => $validated['growth_areas'] ?? [],
            ]),
            'ls_onsite_lva' => array_merge($base, [
                'observe_set' => $validated['observe_set'] ?? null,
                'observe_day' => $validated['observe_day'] ?? null,
                'observe_summary_extra' => $validated['observe_summary_extra'] ?? null,
                'observe_class' => $validated['observe_class'] ?? null,
                'observe_age' => $validated['observe_age'] ?? null,
                'teacher_experience' => $validated['teacher_experience'] ?? null,
                'session_number' => $validated['session_number'] ?? null,
                'semester_label' => $validated['semester_label'] ?? null,
                'interview_date' => $validated['interview_date'] ?? null,
                'interview_time' => $validated['interview_time'] ?? null,
                'method' => $validated['method'] ?? null,
                'other_notes' => $validated['other_notes'] ?? null,
                'lesson_length_minutes' => $validated['lesson_length_minutes'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'teacher_strengths' => $validated['teacher_strengths'] ?? null,
                'areas_of_concerns' => $validated['areas_of_concerns'] ?? null,
                'next_step' => $validated['next_step'] ?? null,
            ]),
            'pro_con', 'littleseed_con' => array_merge($base, [
                'teacher_experience' => $validated['teacher_experience'] ?? null,
                'session_number' => $validated['session_number'] ?? null,
                'semester_label' => $validated['semester_label'] ?? null,
                'interview_date' => $validated['interview_date'] ?? null,
                'interview_time' => $validated['interview_time'] ?? null,
                'method' => $validated['method'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'teacher_issue' => $validated['teacher_issue'] ?? null,
                'discussion_content' => $validated['discussion_content'] ?? null,
                'solution_plan' => $validated['solution_plan'] ?? null,
            ]),
            'open_class' => array_merge($base, [
                'teacher_experience' => $validated['teacher_experience'] ?? null,
                'session_number' => $validated['session_number'] ?? null,
                'semester_label' => $validated['semester_label'] ?? null,
                'interview_date' => $validated['interview_date'] ?? null,
                'interview_time' => $validated['interview_time'] ?? null,
                'method' => $validated['method'] ?? null,
                'progress_unit' => $validated['progress_unit'] ?? null,
                'progress_lesson' => $validated['progress_lesson'] ?? null,
                'progress_other' => $validated['progress_other'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'support_content' => $validated['support_content'] ?? [],
                'remarks' => $validated['remarks'] ?? null,
            ]),
            'unit21_plus', 'unit31_plus' => array_merge($base, [
                'teacher_experience' => $validated['teacher_experience'] ?? null,
                'session_number' => $validated['session_number'] ?? null,
                'semester_label' => $validated['semester_label'] ?? null,
                'interview_date' => $validated['interview_date'] ?? null,
                'interview_time' => $validated['interview_time'] ?? null,
                'method' => $validated['method'] ?? null,
                'progress_unit' => $validated['progress_unit'] ?? null,
                'progress_lesson' => $validated['progress_lesson'] ?? null,
                'progress_other' => $validated['progress_other'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'verbal_materials' => $validated['verbal_materials'] ?? [],
                'language_arts_materials' => $validated['language_arts_materials'] ?? [],
                'verbal_comments' => $validated['verbal_comments'] ?? null,
                'language_arts_comments' => $validated['language_arts_comments'] ?? null,
                'overall_comments' => $validated['overall_comments'] ?? null,
            ]),
            default => throw new InvalidArgumentException('지원하지 않는 교사 지원 보고서 유형입니다.'),
        };
    }
}
