<?php

namespace App\Actions;

use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

class UpdateTeacherProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $teacherId, array $data, User $user): Teacher
    {
        $teacher = Teacher::findOrFail($teacherId);

        $this->authorize($teacher, $user);

        $validated = $this->validate($data);

        $profileCols = config('coach_teacher_support.profile_columns');

        $attributes = [];
        foreach ($validated as $key => $value) {
            $column = $profileCols[$key] ?? null;
            if (! $column) {
                continue;
            }

            $attributes[$column] = $value;
        }

        $teacher->update($attributes);

        return $teacher->refresh();
    }

    private function authorize(Teacher $teacher, User $user): void
    {
        if ($user->hasFullAccess()) {
            return;
        }

        $query = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($query, $user);

        if (! $query->exists()) {
            throw new AuthorizationException('이 교사의 프로필을 수정할 권한이 없습니다.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        return Validator::make($data, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'class_in_out' => ['sometimes', 'boolean'],
            'gs_essentials' => ['sometimes', 'nullable', 'date'],
            'ls_essentials' => ['sometimes', 'nullable', 'date'],
            'unit_21' => ['sometimes', 'nullable', 'date'],
            'unit_31' => ['sometimes', 'nullable', 'date'],
            'gs_connect' => ['sometimes', 'nullable', 'date'],
            'nexus' => ['sometimes', 'nullable', 'date'],
            'certi_gs' => ['sometimes', 'nullable', 'boolean'],
            'certi_ls' => ['sometimes', 'nullable', 'boolean'],
            'ls_support' => ['sometimes', 'nullable', 'date'],
        ])->validate();
    }
}
