<?php

namespace App\Actions;

use App\Enums\TeacherEmploymentType;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateTeacherProfile
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(int $teacherId, array $data, User $user): Teacher
    {
        $teacher = Teacher::findOrFail($teacherId);

        $this->authorize($teacher, $user);

        $data = $this->normalizeIncomingData($data);

        $validated = $this->validate($data, $teacher);

        $profileCols = config('coach_teacher_support.profile_columns');

        $attributes = [];
        foreach ($validated as $key => $value) {
            $column = $profileCols[$key] ?? null;
            if (! $column) {
                continue;
            }

            $attributes[$column] = $value instanceof TeacherEmploymentType
                ? $value->value
                : $value;
        }

        $teacher->update($attributes);

        return $teacher->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeIncomingData(array $data): array
    {
        if (array_key_exists('email', $data)) {
            $email = trim((string) ($data['email'] ?? ''));
            $data['email'] = $email === '' ? null : $email;
        }

        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) ($data['name'] ?? ''));
        }

        return $data;
    }

    private function authorize(Teacher $teacher, User $user): void
    {
        if (CoachTeacherScope::allowsWrite($teacher, $user)) {
            return;
        }

        throw new AuthorizationException('이 교사의 프로필을 수정할 권한이 없습니다.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(array $data, Teacher $teacher): array
    {
        $emailRules = ['sometimes', 'nullable', 'email', 'max:190'];

        if (array_key_exists('email', $data) && $this->emailChanged($data['email'] ?? null, $teacher->Email)) {
            $emailRules[] = Rule::unique('Teachers', 'Email')->ignore($teacher->ID, 'ID');
        }

        return Validator::make($data, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => $emailRules,
            'phone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'class_in_out' => ['sometimes', 'boolean'],
            'employment_type' => ['sometimes', 'nullable', Rule::enum(TeacherEmploymentType::class)],
            'gs_essentials' => ['sometimes', 'nullable', 'date'],
            'ls_essentials' => ['sometimes', 'nullable', 'date'],
            'unit_21' => ['sometimes', 'nullable', 'date'],
            'unit_31' => ['sometimes', 'nullable', 'date'],
            'gs_connect' => ['sometimes', 'nullable', 'date'],
            'nexus' => ['sometimes', 'nullable', 'date'],
            'certi_gs' => ['sometimes', 'nullable', 'boolean'],
            'certi_ls' => ['sometimes', 'nullable', 'boolean'],
            'ls_support' => ['sometimes', 'nullable', 'date'],
        ], [
            'email.email' => '올바른 이메일 형식이 아닙니다.',
            'email.unique' => '이미 등록된 이메일입니다.',
            'description.max' => '비고는 5,000자 이내로 입력해 주세요.',
        ])->validate();
    }

    private function emailChanged(mixed $incoming, mixed $original): bool
    {
        $incomingEmail = mb_strtolower(trim((string) ($incoming ?? '')));

        if ($incomingEmail === '') {
            return false;
        }

        return $incomingEmail !== mb_strtolower(trim((string) ($original ?? '')));
    }
}
