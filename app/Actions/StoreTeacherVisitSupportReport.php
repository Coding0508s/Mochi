<?php

namespace App\Actions;

use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\TeacherVisitSupportReport;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\CoachTeacherSupportPayload;
use App\Support\NullableFormInteger;
use App\Support\SupportReportStoredMailNotifier;
use App\Support\TeacherSupportReportSupportRecordBuilder;
use App\Support\TeacherSupportSlotSync;
use App\Support\TeamMenuContext;
use App\Support\VisitSupportReportUniquenessGuard;
use App\Support\VisitSupportReportValidationPresenter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreTeacherVisitSupportReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $teacherId, array $data, User $user): TeacherVisitSupportReport
    {
        $teacher = Teacher::findOrFail($teacherId);
        $this->authorize($teacher, $user);

        $validated = $this->validate($data);
        $validated = CoachTeacherSupportPayload::applyTrustedContext($validated, $teacher);
        $markCompleted = (bool) ($validated['mark_completed'] ?? false);
        $status = $markCompleted ? '완료' : '임시';

        return DB::transaction(function () use ($teacher, $validated, $user, $status, $markCompleted): TeacherVisitSupportReport {
            if ($markCompleted) {
                VisitSupportReportUniquenessGuard::assertNoCompletedDuplicate(
                    (int) $teacher->ID,
                    $validated['support_date'] ?? null,
                );
            }

            $supportRecord = SupportRecord::query()->create(
                TeacherSupportReportSupportRecordBuilder::build('visit', $validated) + [
                    'CreatedDate' => now(),
                    ...SupportRecord::completionAttributes($markCompleted),
                ]
            );
            $supportRecordId = (int) $supportRecord->ID;

            if ($markCompleted) {
                $isNewTeacherSupport = (bool) ($validated['is_new_teacher_support'] ?? false);
                $round = isset($validated['support_round']) ? (int) $validated['support_round'] : null;
                if ($round === null && ! $isNewTeacherSupport) {
                    $round = TeacherSupportSlotSync::firstEmptyRound($teacher);
                }

                if ($round !== null) {
                    TeacherSupportSlotSync::apply(
                        $teacher,
                        $round,
                        (string) config('coach_teacher_visit.support_type_label'),
                        $validated['support_date'] ?? null,
                    );
                }

                DB::afterCommit(function () use ($supportRecord, $user): void {
                    SupportReportStoredMailNotifier::send(
                        $supportRecord,
                        $user,
                        teamMenu: TeamMenuContext::MENU_COACH,
                        reportMode: 'teacher',
                    );
                });
            }

            return TeacherVisitSupportReport::query()->create([
                'teacher_id' => $teacher->ID,
                'sk_code' => $validated['sk_code'],
                'coach_name' => $validated['coach_name'],
                'institution_name' => $validated['institution_name'],
                'teacher_name' => $validated['teacher_name'],
                'support_date' => $validated['support_date'],
                'support_location' => $validated['support_location'] ?? null,
                'support_purpose' => $validated['support_purpose'],
                'observe_unit' => $validated['observe_unit'] ?? null,
                'observe_lesson' => $validated['observe_lesson'] ?? null,
                'observe_summary_extra' => $validated['observe_summary_extra'] ?? null,
                'observe_class' => $validated['observe_class'] ?? null,
                'observe_age' => $validated['observe_age'] ?? null,
                'session_number' => $validated['session_number'] ?? null,
                'semester_label' => $validated['semester_label'] ?? null,
                'interview_date' => $validated['interview_date'] ?? null,
                'interview_time' => $validated['interview_time'] ?? null,
                'meeting_type' => $validated['meeting_type'] ?? null,
                'pre_request_notes' => $validated['pre_request_notes'] ?? null,
                'monitoring_feedback' => $validated['monitoring_feedback'] ?? null,
                'interview_and_action_plan' => $validated['interview_and_action_plan'] ?? null,
                'special_notes' => $validated['special_notes'] ?? null,
                'status' => $status,
                'support_record_id' => $supportRecordId,
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validatedPayload(array $data): array
    {
        return $this->validate($data);
    }

    private function authorize(Teacher $teacher, User $user): void
    {
        if ($user->hasFullAccess()) {
            return;
        }

        $query = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($query, $user);

        if (! $query->exists()) {
            throw new AuthorizationException('이 교사에 대한 지원 및 참관 보고서를 작성할 권한이 없습니다.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        $data = NullableFormInteger::normalizePayload($data);

        $markCompleted = (bool) ($data['mark_completed'] ?? false);

        return Validator::make(
            $data,
            [
                'sk_code' => ['required', 'string', 'max:100'],
                'coach_name' => ['required', 'string', 'max:255'],
                'institution_name' => ['required', 'string', 'max:255'],
                'teacher_name' => ['required', 'string', 'max:255'],
                'support_date' => ['required', 'date'],
                'support_location' => ['nullable', 'string', 'max:255'],
                'support_purpose' => ['required', 'string', 'max:100'],
                'observe_unit' => ['nullable', 'integer', 'min:0', 'max:99'],
                'observe_lesson' => ['nullable', 'integer', 'min:0', 'max:99'],
                'observe_summary_extra' => ['nullable', 'string', 'max:255'],
                'observe_class' => ['nullable', 'string', 'max:50'],
                'observe_age' => ['nullable', 'string', 'max:50'],
                'session_number' => ['nullable', 'integer', 'min:1', 'max:9'],
                'semester_label' => ['nullable', 'string', 'max:100'],
                'interview_date' => ['nullable', 'date'],
                'interview_time' => ['nullable', 'string', 'max:10'],
                'meeting_type' => ['nullable', 'string', 'max:50'],
                'pre_request_notes' => ['nullable', 'string', 'max:1000'],
                'monitoring_feedback' => array_filter([
                    $markCompleted ? 'required' : 'nullable',
                    'string',
                    'max:2000',
                ]),
                'interview_and_action_plan' => ['nullable', 'string', 'max:2000'],
                'special_notes' => ['nullable', 'string', 'max:1000'],
                'mark_completed' => ['nullable', 'boolean'],
                'is_new_teacher_support' => ['nullable', 'boolean'],
                'support_round' => ['nullable', 'integer', 'between:1,4'],
            ],
            VisitSupportReportValidationPresenter::messages(),
        )->validate();
    }
}
