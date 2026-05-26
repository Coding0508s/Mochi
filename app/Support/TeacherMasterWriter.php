<?php

namespace App\Support;

use App\Models\Teacher;
use App\Models\TeacherMasterDb;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class TeacherMasterWriter
{
    public function recordFromTeacher(Teacher $teacher, User $user): ?TeacherMasterDb
    {
        if (! $this->hasTable()) {
            return null;
        }

        $teacher->loadMissing(['institution.accountInfo']);

        $columns = $this->columns();
        $now = now();
        $userEmail = (string) ($user->email ?? '');

        $payload = $this->filterExistingColumns([
            $columns['name'] => $teacher->Name,
            $columns['sk_code'] => $teacher->SK_Code,
            $columns['school_name'] => $teacher->School_Name,
            $columns['status'] => config('coach_retired_teachers.statuses.retired', '퇴직'),
            $columns['tr_name'] => (string) ($teacher->institution?->accountInfo?->TR ?? ''),
            $columns['email'] => $teacher->Email,
            $columns['phone'] => $teacher->Phone,
            $columns['gs_essentials'] => $teacher->GrapeSEEDEssentials,
            $columns['ls_essentials'] => $teacher->LittleSEEDEssentials,
            $columns['description'] => $teacher->Description,
            $columns['retired_at'] => $now,
            'FGC_LastModifier' => $userEmail,
            'FGC_LastModifyDate' => $now,
        ]);

        $record = TeacherMasterDb::findByTeacherId($teacher->ID);
        if ($record) {
            $record->update($payload);

            return $record->refresh();
        }

        $createPayload = $payload;
        $teacherIdColumn = (new TeacherMasterDb)->teacherIdColumn();
        if ($this->hasColumn($teacherIdColumn)) {
            $createPayload[$teacherIdColumn] = $teacher->ID;
        }
        if ($this->hasColumn('FGC_Creator')) {
            $createPayload['FGC_Creator'] = $userEmail;
        }
        if ($this->hasColumn('FGC_CreateDate')) {
            $createPayload['FGC_CreateDate'] = $now;
        }

        return TeacherMasterDb::query()->create($createPayload);
    }

    public function markReinstatedFromTeacher(Teacher $teacher, User $user): ?TeacherMasterDb
    {
        if (! $this->hasTable()) {
            return null;
        }

        $record = TeacherMasterDb::findByTeacherId($teacher->ID);
        if (! $record) {
            return null;
        }

        $columns = $this->columns();
        $payload = $this->filterExistingColumns([
            $columns['status'] => config('coach_retired_teachers.statuses.teacher_active', '활성화'),
            'FGC_LastModifier' => (string) ($user->email ?? ''),
            'FGC_LastModifyDate' => now(),
        ]);

        $record->update($payload);

        return $record->refresh();
    }

    public function deleteForTeacher(int $teacherId): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        $record = TeacherMasterDb::findByTeacherId($teacherId);
        if (! $record) {
            return 0;
        }

        return (int) $record->delete();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function filterExistingColumns(array $values): array
    {
        $filtered = [];

        foreach ($values as $column => $value) {
            if (is_string($column) && $column !== '' && $this->hasColumn($column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, string>
     */
    private function columns(): array
    {
        return array_merge([
            'id' => 'ID',
            'teacher_id' => 'TeacherID',
            'name' => 'Name',
            'sk_code' => 'SK_Code',
            'school_name' => 'School_Name',
            'status' => 'Status',
            'retired_at' => 'RetirementDate',
            'tr_name' => 'TR_Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'gs_essentials' => 'GrapeSEEDEssentials',
            'ls_essentials' => 'LittleSEEDEssentials',
            'description' => 'Description',
        ], config('coach_retired_teachers.teacher_master.columns', []));
    }

    private function hasTable(): bool
    {
        return Schema::hasTable(config('coach_retired_teachers.teacher_master.table', 'S_TeacherMasterDB'));
    }

    private function hasColumn(string $column): bool
    {
        return Schema::hasColumn(config('coach_retired_teachers.teacher_master.table', 'S_TeacherMasterDB'), $column);
    }
}
