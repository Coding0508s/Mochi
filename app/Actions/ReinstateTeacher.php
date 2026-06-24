<?php

namespace App\Actions;

use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\RetirementListWriter;
use App\Support\TeacherMasterWriter;
use App\Support\TeamMenuContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReinstateTeacher
{
    /**
     * 퇴직 교사를 복직 처리합니다.
     *
     * $newSkCode가 주어지면 해당 기관으로 소속을 옮겨 복직합니다(null이면 기존 기관 유지).
     * 퇴직 당시 기관 스냅샷(S_RetirementList의 SK_Code/Account_Name)은 변경하지 않아
     * 전 근무 기관 기록이 보존됩니다.
     */
    public function execute(int $teacherId, User $user, bool $classInOut, ?string $newSkCode = null): Teacher
    {
        $teacher = Teacher::findOrFail($teacherId);

        if (! $teacher->isRetired()) {
            throw new InvalidArgumentException('퇴직 상태인 교사만 복직 처리할 수 있습니다.');
        }

        $this->authorize($teacher, $user);

        $newInstitution = $this->resolveNewInstitution($teacher, $newSkCode);

        return DB::transaction(function () use ($teacher, $user, $classInOut, $newInstitution): Teacher {
            $attributes = [
                'Status' => config('coach_retired_teachers.statuses.teacher_active', '활성화'),
                'ClassInOut' => $classInOut,
            ];

            if ($newInstitution !== null) {
                $attributes['SK_Code'] = (string) $newInstitution->SKcode;
                $attributes['School_Name'] = $newInstitution->resolvedAccountName();
            }

            $teacher->update($attributes);

            $teacher->refresh();

            app(RetirementListWriter::class)->markReinstatedFromTeacher($teacher, $user);
            app(TeacherMasterWriter::class)->markReinstatedFromTeacher($teacher, $user);

            return $teacher;
        });
    }

    /**
     * 복직할 새 기관을 찾습니다. 기존 기관과 같거나 미지정이면 null(변경 없음)을 반환합니다.
     */
    private function resolveNewInstitution(Teacher $teacher, ?string $newSkCode): ?Institution
    {
        $skCode = trim((string) $newSkCode);
        if ($skCode === '' || $skCode === trim((string) $teacher->SK_Code)) {
            return null;
        }

        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $skCode)
            ->first();

        if (! $institution) {
            throw new InvalidArgumentException('선택한 복직 기관을 찾을 수 없습니다. 기관을 다시 선택해 주세요.');
        }

        return $institution;
    }

    private function authorize(Teacher $teacher, User $user): void
    {
        if ($user->hasFullAccess() || TeamMenuContext::isAdministrationTeam($user)) {
            return;
        }

        $query = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($query, $user);

        if (! $query->exists()) {
            throw new AuthorizationException('이 교사의 복직 처리 권한이 없습니다.');
        }
    }
}
