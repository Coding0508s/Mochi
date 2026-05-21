<?php

namespace App\Actions;

use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateTeacherSupport
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function execute(int $teacherId, array $data, User $user): Teacher
    {
        $teacher = Teacher::findOrFail($teacherId);

        $this->authorize($teacher, $user);

        $validated = $this->validate($data);

        $cols = config('coach_teacher_support.columns');

        $fillData = [
            $cols['plan_1st'] => $validated['plan_1st'] ?: null,
            $cols['plan_2nd'] => $validated['plan_2nd'] ?: null,
            $cols['plan_type_1st'] => $validated['plan_type_1st'] ?: null,
            $cols['plan_type_2nd'] => $validated['plan_type_2nd'] ?: null,
            $cols['completed_1st'] => $validated['completed_1st'] ?: null,
            $cols['completed_2nd'] => $validated['completed_2nd'] ?: null,
            $cols['completed_3rd'] => $validated['completed_3rd'] ?: null,
            $cols['completed_4th'] => $validated['completed_4th'] ?: null,
            $cols['type_1st'] => $validated['type_1st'] ?: null,
            $cols['type_2nd'] => $validated['type_2nd'] ?: null,
            $cols['type_3rd'] => $validated['type_3rd'] ?: null,
            $cols['type_4th'] => $validated['type_4th'] ?: null,
            $cols['essentials_gs'] => $validated['essentials_gs'] ?: null,
            $cols['essentials_ls'] => $validated['essentials_ls'] ?: null,
        ];

        $teacher->update($fillData);

        return $teacher->fresh();
    }

    private function authorize(Teacher $teacher, User $user): void
    {
        if ($user->hasFullAccess()) {
            return;
        }

        $scopedQuery = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($scopedQuery, $user);

        if (! $scopedQuery->exists()) {
            throw new AuthorizationException(
                '이 교사의 지원 일정을 수정할 권한이 없습니다.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        $planTypeRule = ['nullable', 'string', 'max:100'];
        $completionTypeRule = ['nullable', 'string', 'max:100'];

        return Validator::make($data, [
            'plan_1st' => ['nullable', 'date'],
            'plan_2nd' => ['nullable', 'date'],
            'plan_type_1st' => $planTypeRule,
            'plan_type_2nd' => $planTypeRule,
            'completed_1st' => ['nullable', 'date'],
            'completed_2nd' => ['nullable', 'date'],
            'completed_3rd' => ['nullable', 'date'],
            'completed_4th' => ['nullable', 'date'],
            'type_1st' => $completionTypeRule,
            'type_2nd' => $completionTypeRule,
            'type_3rd' => $completionTypeRule,
            'type_4th' => $completionTypeRule,
            'essentials_gs' => ['nullable', 'date'],
            'essentials_ls' => ['nullable', 'date'],
        ])->validate();
    }
}
