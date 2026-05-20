<?php

namespace App\Support;

use App\Models\SupportRecord;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeacherSupportHistoryAggregator
{
    /**
     * @return list<array{id: int|string, coach: string, teacher: string, date: string, status: string, type: string, sort_at: int}>
     */
    public function forTeacher(Teacher $teacher): array
    {
        $linkedAccountIds = $this->linkedSupportRecordIdsForTeacher($teacher->ID);

        $records = collect();
        $records = $records->merge($this->fromLegacyTables(teacherId: $teacher->ID));
        $records = $records->merge($this->fromSupportInfoAccount(
            teacherName: (string) $teacher->Name,
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code),
            excludeAccountIds: $linkedAccountIds,
        ));
        $records = $records->merge($this->fromMochiReportTables(teacherId: $teacher->ID));

        return $this->finalize($records);
    }

    /**
     * @param  list<string>  $candidateSkCodes
     * @return list<array{id: int|string, coach: string, teacher: string, date: string, status: string, type: string, sort_at: int}>
     */
    public function forInstitution(array $candidateSkCodes, int $limit = 10): array
    {
        if ($candidateSkCodes === []) {
            return [];
        }

        $records = collect();

        foreach ($candidateSkCodes as $skCode) {
            $records = $records->merge($this->fromLegacyTables(skCode: $skCode));
            $records = $records->merge($this->fromSupportInfoAccount(skCode: $skCode));
        }

        $teacherIds = Teacher::query()
            ->whereIn('SK_Code', $candidateSkCodes)
            ->where('ClassInOut', true)
            ->pluck('ID');

        foreach ($teacherIds as $teacherId) {
            $records = $records->merge($this->fromMochiReportTables(teacherId: (int) $teacherId));
        }

        return $this->finalize($records, $limit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromLegacyTables(?int $teacherId = null, ?string $skCode = null): Collection
    {
        $records = collect();

        foreach (config('coach_teacher_legacy_support.legacy_sources', []) as $source) {
            $table = $source['table'] ?? null;
            if (! is_string($table) || ! Schema::hasTable($table)) {
                continue;
            }

            $teacherIdColumn = $this->legacyTeacherIdColumn($table);
            if ($teacherIdColumn === null) {
                continue;
            }

            $query = DB::table($table);

            if ($teacherId !== null) {
                $query->where($teacherIdColumn, $teacherId);
            }

            if ($skCode !== null && Schema::hasColumn($table, 'SK_Code')) {
                $query->where('SK_Code', $skCode);
            }

            $rows = $query->get($this->legacySelectColumns($table));

            foreach ($rows as $row) {
                $records->push($this->mapLegacyRow($row, $source));
            }
        }

        return $records;
    }

    /**
     * @param  list<int>  $excludeAccountIds
     * @return Collection<int, array<string, mixed>>
     */
    private function fromSupportInfoAccount(
        ?string $teacherName = null,
        ?string $skCode = null,
        array $excludeAccountIds = [],
    ): Collection {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return collect();
        }

        $query = SupportRecord::query();

        if (filled($skCode)) {
            $query->where('SK_Code', $skCode);
        }

        if ($excludeAccountIds !== []) {
            $query->whereNotIn('ID', $excludeAccountIds);
        }

        if (filled($teacherName)) {
            $normalizedName = $this->normalizeName($teacherName);
            $query->where(function ($q) use ($teacherName, $normalizedName): void {
                $q->where('Target', $teacherName)
                    ->orWhereRaw("REPLACE(Target, ' ', '') = ?", [$normalizedName]);
            });
        } else {
            $query->whereNotNull('Target')->where('Target', '!=', '');
        }

        return $query
            ->orderByDesc('Support_Date')
            ->get(['ID', 'TR_Name', 'Target', 'Support_Date', 'Support_Type', 'Status'])
            ->map(fn (SupportRecord $record) => [
                'id' => $record->ID,
                'coach' => (string) ($record->TR_Name ?? ''),
                'teacher' => (string) ($record->Target ?? ''),
                'date' => $this->formatDisplayDate($record->Support_Date),
                'status' => (string) ($record->Status ?? '완료'),
                'type' => (string) ($record->Support_Type ?? ''),
                'sort_at' => $record->Support_Date?->getTimestamp() ?? 0,
                'dedupe_key' => 'account:'.$record->ID,
                'detail_key' => 'account:'.$record->ID,
                'teacher_id' => null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromMochiReportTables(int $teacherId): Collection
    {
        $records = collect();

        foreach (config('coach_teacher_legacy_support.mochi_report_tables', []) as $table => $typeLabel) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->where('teacher_id', $teacherId)
                ->orderByDesc('support_date')
                ->get(['id', 'coach_name', 'teacher_name', 'support_date', 'status', 'support_record_id']);

            foreach ($rows as $row) {
                $records->push([
                    'id' => 'M'.$row->id,
                    'coach' => (string) ($row->coach_name ?? ''),
                    'teacher' => (string) ($row->teacher_name ?? ''),
                    'date' => $this->formatDisplayDate($row->support_date),
                    'status' => (string) ($row->status ?? '임시'),
                    'type' => $typeLabel,
                    'sort_at' => $this->parseSortTimestamp($row->support_date),
                    'dedupe_key' => $table.':'.$row->id,
                    'detail_key' => 'mochi:'.$table.':'.$row->id,
                    'teacher_id' => (int) $teacherId,
                ]);
            }
        }

        return $records;
    }

    /**
     * @return list<int>
     */
    private function linkedSupportRecordIdsForTeacher(int $teacherId): array
    {
        $ids = [];

        foreach (array_keys(config('coach_teacher_legacy_support.mochi_report_tables', [])) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $tableIds = DB::table($table)
                ->where('teacher_id', $teacherId)
                ->whereNotNull('support_record_id')
                ->pluck('support_record_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $ids = array_merge($ids, $tableIds);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function mapLegacyRow(object $row, array $source): array
    {
        $type = $source['type'] ?? '교사 지원';

        if (($source['type_resolver'] ?? null) === 'lva') {
            $lvaType = $row->LVA_TYPE ?? null;
            if (! filled($lvaType) && isset($row->ReportType)) {
                $lvaType = config('coach_teacher_legacy_support.lva_report_types')[(int) $row->ReportType] ?? null;
            }
            $type = filled($lvaType) ? '교사 지원 LVA '.$lvaType : '교사 지원 LVA';
        }

        $table = (string) ($source['table'] ?? 'legacy');
        $teacherIdColumn = $this->legacyTeacherIdColumn($table);

        return [
            'id' => $row->ID,
            'coach' => (string) ($row->TR_Name ?? ''),
            'teacher' => (string) ($row->Teacher ?? ''),
            'date' => $this->formatDisplayDate($row->SupportDate),
            'status' => (string) ($row->Status ?? '완료'),
            'type' => $type,
            'sort_at' => $this->parseSortTimestamp($row->SupportDate),
            'dedupe_key' => $table.':'.$row->ID,
            'detail_key' => 'legacy:'.$table.':'.$row->ID,
            'teacher_id' => $teacherIdColumn ? (int) ($row->{$teacherIdColumn} ?? 0) : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return list<array{id: int|string, coach: string, teacher: string, date: string, status: string, type: string}>
     */
    private function finalize(Collection $records, ?int $limit = null): array
    {
        $deduped = $records
            ->unique(fn (array $record) => $record['dedupe_key'] ?? implode('|', [
                $record['id'],
                $record['date'],
                $record['type'],
                $record['coach'],
            ]))
            ->sortByDesc('sort_at')
            ->values();

        if ($limit !== null) {
            $deduped = $deduped->take($limit);
        }

        return $deduped
            ->map(fn (array $record) => [
                'id' => $record['id'],
                'coach' => $record['coach'],
                'teacher' => $record['teacher'],
                'date' => $record['date'],
                'status' => $record['status'],
                'type' => $record['type'],
                'detail_key' => $record['detail_key'] ?? null,
                'teacher_id' => $record['teacher_id'] ?? null,
            ])
            ->all();
    }

    private function formatDisplayDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('n/j/Y H:i:s');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function parseSortTimestamp(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', '', $name) ?? '';
    }

    private function legacyTeacherIdColumn(string $table): ?string
    {
        foreach (['TeacherId', 'TeacherID'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function legacySelectColumns(string $table): array
    {
        $candidates = ['ID', 'TR_Name', 'Teacher', 'SupportDate', 'Status', 'ReportType', 'LVA_TYPE'];

        return array_values(array_filter(
            $candidates,
            fn (string $column) => Schema::hasColumn($table, $column),
        ));
    }
}
