<?php

namespace App\Actions;

use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\RetirementListWriter;
use App\Support\TeacherMasterWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReinstateTeacher
{
    public function execute(int $teacherId, User $user, bool $classInOut): Teacher
    {
        $teacher = Teacher::findOrFail($teacherId);

        if (! $teacher->isRetired()) {
            throw new InvalidArgumentException('퇴직 상태인 교사만 복직 처리할 수 있습니다.');
        }

        $this->authorize($teacher, $user);

        return DB::transaction(function () use ($teacher, $user, $classInOut): Teacher {
            $teacher->update([
                'Status' => config('coach_retired_teachers.statuses.teacher_active', '활성화'),
                'ClassInOut' => $classInOut,
            ]);

            $teacher->refresh();

            app(RetirementListWriter::class)->markReinstatedFromTeacher($teacher, $user);
            app(TeacherMasterWriter::class)->markReinstatedFromTeacher($teacher, $user);

            return $teacher;
        });
    }

    private function authorize(Teacher $teacher, User $user): void
    {
        if ($user->hasFullAccess()) {
            return;
        }

        $query = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($query, $user);

        if (! $query->exists()) {
            throw new AuthorizationException('이 교사의 복직 처리 권한이 없습니다.');
        }
    }
}
