<?php

namespace App\Support;

use App\Models\Teacher;
use App\Models\TeacherDemoLessonSupportReport;
use App\Models\TeacherLittleseedConSupportReport;
use App\Models\TeacherLsOnsiteLvaSupportReport;
use App\Models\TeacherLvaFbSupportReport;
use App\Models\TeacherLvaFrSupportReport;
use App\Models\TeacherOnsiteSupportReport;
use App\Models\TeacherOpenClassSupportReport;
use App\Models\TeacherProConSupportReport;
use App\Models\TeacherUnit21PlusSupportReport;
use App\Models\TeacherUnit31PlusSupportReport;
use App\Models\TeacherVisitSupportReport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TeacherSupportReportEditAuthorization
{
    /** @var array<string, class-string<Model>> */
    private const MODEL_CLASSES = [
        'teacher_demo_lesson_support_reports' => TeacherDemoLessonSupportReport::class,
        'teacher_lva_fr_support_reports' => TeacherLvaFrSupportReport::class,
        'teacher_lva_fb_support_reports' => TeacherLvaFbSupportReport::class,
        'teacher_ls_onsite_lva_support_reports' => TeacherLsOnsiteLvaSupportReport::class,
        'teacher_littleseed_con_support_reports' => TeacherLittleseedConSupportReport::class,
        'teacher_onsite_support_reports' => TeacherOnsiteSupportReport::class,
        'teacher_pro_con_support_reports' => TeacherProConSupportReport::class,
        'teacher_open_class_support_reports' => TeacherOpenClassSupportReport::class,
        'teacher_unit21_plus_support_reports' => TeacherUnit21PlusSupportReport::class,
        'teacher_unit31_plus_support_reports' => TeacherUnit31PlusSupportReport::class,
        'teacher_visit_support_reports' => TeacherVisitSupportReport::class,
    ];

    public static function findMochiReport(string $table, int $reportId): ?Model
    {
        $modelClass = self::MODEL_CLASSES[$table] ?? null;
        if ($modelClass === null) {
            return null;
        }

        return $modelClass::query()->find($reportId);
    }

    /** @var list<string> */
    private const LEGACY_TABLES = [
        'S_Support_NewTeacher',
        'S_Support_LVA',
        'S_Support_OnSite',
        'S_Support_OpenClass',
        'S_SupportLittleSEED_ONLVA',
        'S_Support_U21',
        'S_Support_U31',
        'S_SolutionConsulting',
    ];

    public static function isLegacyTable(string $table): bool
    {
        return in_array($table, self::LEGACY_TABLES, true);
    }

    public static function findLegacyReport(string $table, int $reportId): ?object
    {
        if (! self::isLegacyTable($table) || ! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->where('ID', $reportId)->first();
    }

    public static function legacyTeacherIdFromRow(string $table, object $row): ?int
    {
        $column = self::legacyTeacherIdColumn($table);
        if ($column === null) {
            return null;
        }

        $teacherId = (int) ($row->{$column} ?? 0);

        return $teacherId > 0 ? $teacherId : null;
    }

    /**
     * @return array{source: string, table: string, id: int}|null
     */
    public static function parseEditableDetailKey(string $detailKey): ?array
    {
        if (preg_match('/^mochi:([^:]+):(\d+)$/', $detailKey, $matches)) {
            return [
                'source' => 'mochi',
                'table' => $matches[1],
                'id' => (int) $matches[2],
            ];
        }

        if (preg_match('/^legacy:([^:]+):(\d+)$/', $detailKey, $matches)) {
            return [
                'source' => 'legacy',
                'table' => $matches[1],
                'id' => (int) $matches[2],
            ];
        }

        return null;
    }

    public static function canUpdateLegacy(User $user, object $row, Teacher $teacher): bool
    {
        try {
            self::ensureCanUpdateLegacy($user, $row, $teacher);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function ensureCanUpdateLegacy(User $user, object $row, Teacher $teacher): void
    {
        $institution = InstitutionResolver::resolveForTeacher($teacher);
        if ($institution?->isTerminatedCustomer()) {
            throw new AuthorizationException('해지된 기관의 교사 지원 보고서는 수정할 수 없습니다.');
        }

        if ($user->hasFullAccess()) {
            return;
        }

        $scopedQuery = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($scopedQuery, $user);

        if (! $scopedQuery->exists()) {
            throw new AuthorizationException('이 교사 지원 보고서를 수정할 권한이 없습니다.');
        }

        $authorKey = ManagerNameNormalizer::normalize((string) ($row->TR_Name ?? ''));
        $userKey = ManagerNameNormalizer::normalize($user->nameForCoReports());

        if ($authorKey !== '' && $userKey !== '' && $authorKey === $userKey) {
            return;
        }

        throw new AuthorizationException('본인이 작성한 교사 지원 보고서만 수정할 수 있습니다.');
    }

    private static function legacyTeacherIdColumn(string $table): ?string
    {
        foreach (['TeacherId', 'TeacherID'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    public static function isMochiDetailKey(string $detailKey): bool
    {
        return (bool) preg_match('/^mochi:([^:]+):(\d+)$/', $detailKey);
    }

    /**
     * @return array{table: string, id: int}|null
     */
    public static function parseMochiDetailKey(string $detailKey): ?array
    {
        if (! preg_match('/^mochi:([^:]+):(\d+)$/', $detailKey, $matches)) {
            return null;
        }

        return [
            'table' => $matches[1],
            'id' => (int) $matches[2],
        ];
    }

    public static function canUpdate(User $user, Model $report, Teacher $teacher): bool
    {
        try {
            self::ensureCanUpdate($user, $report, $teacher);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function ensureCanUpdate(User $user, Model $report, Teacher $teacher): void
    {
        $institution = InstitutionResolver::resolveForTeacher($teacher);
        if ($institution?->isTerminatedCustomer()) {
            throw new AuthorizationException('해지된 기관의 교사 지원 보고서는 수정할 수 없습니다.');
        }

        if ($user->hasFullAccess()) {
            return;
        }

        $scopedQuery = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($scopedQuery, $user);

        if (! $scopedQuery->exists()) {
            throw new AuthorizationException('이 교사 지원 보고서를 수정할 권한이 없습니다.');
        }

        $createdBy = (int) ($report->getAttribute('created_by') ?? 0);
        if ($createdBy > 0 && $createdBy === (int) $user->id) {
            return;
        }

        $authorKey = ManagerNameNormalizer::normalize((string) ($report->getAttribute('coach_name') ?? ''));
        $userKey = ManagerNameNormalizer::normalize($user->nameForCoReports());

        if ($authorKey !== '' && $userKey !== '' && $authorKey === $userKey) {
            return;
        }

        throw new AuthorizationException('본인이 작성한 교사 지원 보고서만 수정할 수 있습니다.');
    }
}
