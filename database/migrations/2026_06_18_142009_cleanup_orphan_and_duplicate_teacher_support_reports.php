<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $reportTables = [
        'teacher_demo_lesson_support_reports',
        'teacher_lva_fr_support_reports',
        'teacher_lva_fb_support_reports',
        'teacher_ls_onsite_lva_support_reports',
        'teacher_littleseed_con_support_reports',
        'teacher_onsite_support_reports',
        'teacher_pro_con_support_reports',
        'teacher_open_class_support_reports',
        'teacher_unit21_plus_support_reports',
        'teacher_unit31_plus_support_reports',
        'teacher_visit_support_reports',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $typeLabels = config('coach_teacher_legacy_support.mochi_report_tables', []);

        foreach ($this->reportTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            $this->deleteOrphanTeacherRows($table);
            $this->deleteOrphanSupportRecordRows($table, (string) ($typeLabels[$table] ?? ''));
            $this->deleteDuplicateCompletedRowsKeepingLatest($table);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data cleanup migration is irreversible.
    }

    private function deleteOrphanTeacherRows(string $table): void
    {
        if (! Schema::hasTable('Teachers') || ! Schema::hasColumn($table, 'teacher_id')) {
            return;
        }

        DB::table($table)
            ->whereNotNull('teacher_id')
            ->whereNotExists(function ($query) use ($table): void {
                $query->select(DB::raw(1))
                    ->from('Teachers')
                    ->whereColumn('Teachers.ID', $table.'.teacher_id');
            })
            ->delete();
    }

    private function deleteDuplicateCompletedRowsKeepingLatest(string $table): void
    {
        if (
            ! Schema::hasColumn($table, 'teacher_id')
            || ! Schema::hasColumn($table, 'support_date')
            || ! Schema::hasColumn($table, 'status')
        ) {
            return;
        }

        $duplicateGroupQuery = DB::table($table)
            ->selectRaw('teacher_id, support_date, status, MAX(id) as keep_id, COUNT(*) as dup_count')
            ->whereNotNull('teacher_id')
            ->whereNotNull('support_date')
            ->where('status', '완료')
            ->groupBy('teacher_id', 'support_date', 'status')
            ->havingRaw('COUNT(*) > 1');

        $idsToDelete = DB::table($table.' as rows')
            ->joinSub($duplicateGroupQuery, 'dup', function ($join): void {
                $join->on('rows.teacher_id', '=', 'dup.teacher_id')
                    ->on('rows.support_date', '=', 'dup.support_date')
                    ->on('rows.status', '=', 'dup.status')
                    ->whereColumn('rows.id', '!=', 'dup.keep_id');
            })
            ->pluck('rows.id')
            ->all();

        foreach (array_chunk($idsToDelete, 500) as $chunk) {
            DB::table($table)->whereIn('id', $chunk)->delete();
        }
    }

    private function deleteOrphanSupportRecordRows(string $table, string $typeLabel): void
    {
        if (
            ! Schema::hasTable('S_SupportInfo_Account')
            || ! Schema::hasColumn($table, 'support_record_id')
            || ! Schema::hasColumn($table, 'teacher_id')
            || ! Schema::hasColumn($table, 'support_date')
            || ! Schema::hasColumn($table, 'status')
        ) {
            return;
        }

        $rows = DB::table($table)
            ->whereNotNull('support_record_id')
            ->whereNotExists(function ($query) use ($table): void {
                $query->select(DB::raw(1))
                    ->from('S_SupportInfo_Account')
                    ->whereColumn('S_SupportInfo_Account.ID', $table.'.support_record_id');
            })
            ->get(['id', 'teacher_id', 'support_date', 'status']);

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            if ((string) ($row->status ?? '') === '완료') {
                $hasSibling = DB::table($table)
                    ->where('teacher_id', $row->teacher_id)
                    ->whereDate('support_date', $row->support_date)
                    ->where('status', '완료')
                    ->where('id', '!=', $row->id)
                    ->exists();

                if (! $hasSibling) {
                    $this->clearMatchingTeacherCompletion(
                        (int) $row->teacher_id,
                        $typeLabel,
                        (string) $row->support_date
                    );
                }
            }

            DB::table($table)->where('id', $row->id)->delete();
        }
    }

    private function clearMatchingTeacherCompletion(int $teacherId, string $typeLabel, string $supportDate): void
    {
        if ($typeLabel === '' || ! Schema::hasTable('Teachers')) {
            return;
        }

        $roundColumns = [
            ['date' => '_1st_Support_Date', 'type' => '_1st_Support_Type'],
            ['date' => '_2nd_Support_Date', 'type' => '_2nd_Support_Type'],
            ['date' => '_3rd_Support_Date', 'type' => '_3rd_Support_Type'],
            ['date' => '_4th_Support_Date', 'type' => '_4th_Support_Type'],
        ];

        foreach ($roundColumns as $columnSet) {
            if (
                ! Schema::hasColumn('Teachers', $columnSet['date'])
                || ! Schema::hasColumn('Teachers', $columnSet['type'])
            ) {
                continue;
            }

            DB::table('Teachers')
                ->where('ID', $teacherId)
                ->where($columnSet['type'], $typeLabel)
                ->whereDate($columnSet['date'], $supportDate)
                ->update([
                    $columnSet['date'] => null,
                    $columnSet['type'] => null,
                ]);
        }
    }
};
