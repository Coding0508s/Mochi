<?php

namespace App\Actions;

use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\TeacherUnit21PlusSupportReport;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\CoachTeacherSupportPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreTeacherUnit21PlusSupportReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $teacherId, array $data, User $user): TeacherUnit21PlusSupportReport
    {
        $teacher = Teacher::findOrFail($teacherId);
        $this->authorize($teacher, $user);

        $validated = $this->validate($data);
        $validated = CoachTeacherSupportPayload::applyTrustedContext($validated, $teacher);
        $markCompleted = (bool) ($validated['mark_completed'] ?? false);
        $status = $markCompleted ? '완료' : '임시';

        return DB::transaction(function () use ($teacher, $validated, $user, $status, $markCompleted): TeacherUnit21PlusSupportReport {
            $supportRecordId = null;

            if ($markCompleted) {
                $interviewTime = $validated['interview_time'] ?? '00:00';
                $meetTime = strlen($interviewTime) === 5 ? $interviewTime.':00' : $interviewTime;

                $issueSummary = collect([
                    $validated['verbal_comments'] ?? null,
                    $validated['language_arts_comments'] ?? null,
                    $validated['overall_comments'] ?? null,
                ])->filter()->implode("\n\n");

                $supportRecord = SupportRecord::query()->create([
                    'Year' => (int) date('Y', strtotime($validated['support_date'])),
                    'SK_Code' => $validated['sk_code'],
                    'Account_Name' => $validated['institution_name'],
                    'TR_Name' => $validated['coach_name'],
                    'Support_Date' => $validated['support_date'],
                    'Meet_Time' => $meetTime,
                    'Target' => $validated['teacher_name'],
                    'Support_Type' => config('coach_teacher_unit21_plus.support_type_label'),
                    'Issue' => $issueSummary !== '' ? $issueSummary : null,
                    'Status' => '완료',
                    'CreatedDate' => now(),
                    'CompletedDate' => now(),
                ]);
                $supportRecordId = $supportRecord->ID;
            }

            return TeacherUnit21PlusSupportReport::query()->create([
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
                'progress_unit' => $validated['progress_unit'] ?? null,
                'progress_lesson' => $validated['progress_lesson'] ?? null,
                'progress_other' => $validated['progress_other'] ?? null,
                'procedures' => $validated['procedures'] ?? [],
                'verbal_materials' => $validated['verbal_materials'] ?? [],
                'language_arts_materials' => $validated['language_arts_materials'] ?? [],
                'verbal_comments' => $validated['verbal_comments'] ?? null,
                'language_arts_comments' => $validated['language_arts_comments'] ?? null,
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
            throw new AuthorizationException('이 교사에 대한 Unit 21+ 보고서를 작성할 권한이 없습니다.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
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
            'progress_unit' => ['nullable', 'integer', 'min:0', 'max:99'],
            'progress_lesson' => ['nullable', 'integer', 'min:0', 'max:99'],
            'progress_other' => ['nullable', 'string', 'max:255'],
            'procedures' => ['nullable', 'array'],
            'procedures.*' => ['string'],
            'verbal_materials' => ['nullable', 'array'],
            'verbal_materials.*' => ['string'],
            'language_arts_materials' => ['nullable', 'array'],
            'language_arts_materials.*' => ['string'],
            'verbal_comments' => ['nullable', 'string', 'max:5000'],
            'language_arts_comments' => ['nullable', 'string', 'max:5000'],
            'overall_comments' => ['nullable', 'string', 'max:5000'],
            'mark_completed' => ['nullable', 'boolean'],
        ])->validate();
    }
}
