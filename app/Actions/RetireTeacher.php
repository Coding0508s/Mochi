<?php

namespace App\Actions;

use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\RetirementListWriter;
use App\Support\TeacherMasterWriter;
use App\Support\TeacherRetirementRecommendation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RetireTeacher
{
    public function execute(
        int $teacherId,
        User $user,
        ?TeacherRetirementRecommendation $recommendation = null,
    ): Teacher {
        $teacher = Teacher::findOrFail($teacherId);

        $this->authorize($teacher, $user);

        return DB::transaction(function () use ($teacher, $user, $recommendation): Teacher {
            $teacher->update([
                'ClassInOut' => false,
                'Status' => '퇴직',
            ]);

            $teacher->refresh();

            app(RetirementListWriter::class)->recordFromTeacher($teacher, $user, $recommendation);
            app(TeacherMasterWriter::class)->recordFromTeacher($teacher, $user);

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
            throw new AuthorizationException('이 교사의 퇴직 처리 권한이 없습니다.');
        }
    }
}
