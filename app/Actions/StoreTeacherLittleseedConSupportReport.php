<?php

namespace App\Actions;

use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\TeacherLittleseedConSupportReport;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\CoachTeacherSupportPayload;
use App\Support\NullableFormInteger;
use App\Support\TeacherSupportNewTeacherDisplay;
use App\Support\TeacherSupportSlotSync;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreTeacherLittleseedConSupportReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $teacherId, array $data, User $user): TeacherLittleseedConSupportReport
    {
        $teacher = Teacher::findOrFail($teacherId);
        $this->authorize($teacher, $user);

        $validated = $this->validate($data);
        $validated = CoachTeacherSupportPayload::applyTrustedContext($validated, $teacher);
        $markCompleted = (bool) ($validated['mark_completed'] ?? false);
        $status = $markCompleted ? '완료' : '임시';

        return DB::transaction(function () use ($teacher, $validated, $user, $status, $markCompleted): TeacherLittleseedConSupportReport {
            $supportRecordId = null;

            if ($markCompleted) {
                $interviewTime = $validated['interview_time'] ?? '00:00';
                $meetTime = strlen($interviewTime) === 5 ? $interviewTime.':00' : $interviewTime;

                $issueSummary = collect([
                    $validated['teacher_issue'] ?? null,
                    $validated['discussion_content'] ?? null,
                    $validated['solution_plan'] ?? null,
                ])->filter()->implode("\n\n");

                $supportRecord = SupportRecord::query()->create([
                    'Year' => (int) date('Y', strtotime($validated['support_date'])),
                    'SK_Code' => $validated['sk_code'],
                    'Account_Name' => $validated['institution_name'],
                    'TR_Name' => $validated['coach_name'],
                    'Support_Date' => $validated['support_date'],
                    'Meet_Time' => $meetTime,
                    'Target' => $validated['teacher_name'],
                    'Support_Type' => TeacherSupportNewTeacherDisplay::supportTypeForPayload(
                        $validated,
                        (string) config('coach_teacher_littleseed_con.support_type_label'),
                    ),
                    'Issue' => $issueSummary !== '' ? $issueSummary : null,
                    'Status' => '완료',
                    'CreatedDate' => now(),
                    'CompletedDate' => now(),
                ]);
                $supportRecordId = $supportRecord->ID;

                TeacherSupportSlotSync::apply(
                    $teacher,
                    isset($validated['support_round']) ? (int) $validated['support_round'] : null,
                    (string) config('coach_teacher_littleseed_con.support_type_label'),
                );
            }

            return TeacherLittleseedConSupportReport::query()->create([
                'teacher_id' => $teacher->ID,
                'sk_code' => $validated['sk_code'],
                'coach_name' => $validated['coach_name'],
                'institution_name' => $validated['institution_name'],
                'teacher_name' => $validated['teacher_name'],
                'support_date' => $validated['support_date'],
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
            throw new AuthorizationException('이 교사에 대한 LittleSEED Con 보고서를 작성할 권한이 없습니다.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        $data = NullableFormInteger::normalizePayload($data);

        return Validator::make($data, [
            'sk_code' => ['required', 'string', 'max:100'],
            'coach_name' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
            'teacher_name' => ['required', 'string', 'max:255'],
            'support_date' => ['required', 'date'],
            'teacher_experience' => ['nullable', 'string', 'max:50'],
            'session_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'semester_label' => ['nullable', 'string', 'max:100'],
            'interview_date' => ['nullable', 'date'],
            'interview_time' => ['nullable', 'string', 'max:10'],
            'method' => ['nullable', 'string', 'max:50'],
            'procedures' => ['nullable', 'array'],
            'procedures.*' => ['string'],
            'teacher_issue' => ['nullable', 'string', 'max:5000'],
            'discussion_content' => ['nullable', 'string', 'max:5000'],
            'solution_plan' => ['nullable', 'string', 'max:5000'],
            'mark_completed' => ['nullable', 'boolean'],
            'is_new_teacher_support' => ['nullable', 'boolean'],
            'support_round' => ['nullable', 'integer', 'between:1,4'],
        ])->validate();
    }
}
