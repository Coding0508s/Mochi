<?php

namespace App\Actions;

use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use Illuminate\Auth\Access\AuthorizationException;

class RetireTeacher
{
    public function execute(int $teacherId, User $user): Teacher
    {
        $teacher = Teacher::findOrFail($teacherId);

        $this->authorize($teacher, $user);

        $teacher->update([
            'ClassInOut' => false,
            'Status' => '퇴직',
        ]);

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
            throw new AuthorizationException('이 교사의 퇴직 처리 권한이 없습니다.');
        }
    }
}
