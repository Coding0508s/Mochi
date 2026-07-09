<?php

namespace App\Actions;

use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\TeacherDemoLessonSupportReport;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\CoachTeacherSupportPayload;
use App\Support\NullableFormInteger;
use App\Support\TeacherSupportSlotSync;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreTeacherDemoLessonSupportReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $teacherId, array $data, User $user): TeacherDemoLessonSupportReport
    {
        $teacher = Teacher::findOrFail($teacherId);
        $this->authorize($teacher, $user);

        $validated = $this->validate($data);
        $validated = CoachTeacherSupportPayload::applyTrustedContext($validated, $teacher);
        $markCompleted = (bool) ($validated['mark_completed'] ?? false);
        $status = $markCompleted ? '완료' : '임시';

        return DB::transaction(function () use ($teacher, $validated, $user, $status, $markCompleted): TeacherDemoLessonSupportReport {
            $supportRecordId = null;

            if ($markCompleted) {
                $supportRecord = SupportRecord::query()->create([
                    'Year' => (int) date('Y', strtotime($validated['support_date'])),
                    'SK_Code' => $validated['sk_code'],
                    'Account_Name' => $validated['institution_name'],
                    'TR_Name' => $validated['coach_name'],
                    'Support_Date' => $validated['support_date'],
                    'Meet_Time' => '00:00:00',
                    'Target' => $validated['teacher_name'],
                    'Support_Type' => config('coach_teacher_demo_lesson.support_type_label'),
                    'Issue' => $validated['overall_comments'] ?? null,
                    'Others' => $validated['other_notes'] ?? null,
                    'Status' => '완료',
                    'CreatedDate' => now(),
                    'CompletedDate' => now(),
                ]);
                $supportRecordId = $supportRecord->ID;

                TeacherSupportSlotSync::apply(
                    $teacher,
                    isset($validated['support_round']) ? (int) $validated['support_round'] : null,
                    (string) config('coach_teacher_demo_lesson.support_type_label'),
                );
            }

            return TeacherDemoLessonSupportReport::query()->create([
                'teacher_id' => $teacher->ID,
                'sk_code' => $validated['sk_code'],
                'coach_name' => $validated['coach_name'],
                'institution_name' => $validated['institution_name'],
                'teacher_name' => $validated['teacher_name'],
                'support_date' => $validated['support_date'],
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
            throw new AuthorizationException('이 교사에 대한 지원 보고서를 작성할 권한이 없습니다.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        $data = NullableFormInteger::normalizePayload($data);

        $evaluationKeys = array_keys(config('coach_teacher_demo_lesson.evaluation_criteria', []));

        return Validator::make($data, [
            'sk_code' => ['required', 'string', 'max:100'],
            'coach_name' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
            'teacher_name' => ['required', 'string', 'max:255'],
            'support_date' => ['required', 'date'],
            'progress_unit' => ['nullable', 'integer', 'min:0', 'max:99'],
            'progress_lesson' => ['nullable', 'integer', 'min:0', 'max:99'],
            'other_notes' => ['nullable', 'string', 'max:2000'],
            'procedures' => ['nullable', 'array'],
            'procedures.*' => ['string'],
            'verbal_tools' => ['nullable', 'array'],
            'verbal_tools.*' => ['string'],
            'language_arts_tools' => ['nullable', 'array'],
            'language_arts_tools.*' => ['string'],
            'comments_primary' => ['nullable', 'string', 'max:5000'],
            'comments_secondary' => ['nullable', 'string', 'max:5000'],
            'evaluations' => ['nullable', 'array'],
            'evaluations.*' => ['nullable', 'integer', 'in:1,2,3'],
            'overall_comments' => ['nullable', 'string', 'max:5000'],
            'mark_completed' => ['nullable', 'boolean'],
            'is_new_teacher_support' => ['nullable', 'boolean'],
            'support_round' => ['nullable', 'integer', 'between:1,4'],
        ])->validate();
    }
}
