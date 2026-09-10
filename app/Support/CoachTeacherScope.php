<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coach Team 교사 지원 현황 화면에서 사용하는 공통 스코프.
 *
 * - TR 기반 사용자 범위 제한
 * - hidden institution 제외
 * - eager load 정의
 */
final class CoachTeacherScope
{
    /**
     * @param  Builder<Teacher>  $query
     */
    public static function apply(Builder $query, ?User $user = null): void
    {
        $user ??= auth()->user();

        self::excludeHiddenInstitutions($query);

        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (TeamMenuContext::hasExpandedReadScope($user) || TeamMenuContext::hasAdminMenuDataScope($user)) {
            return;
        }

        if ($user->isCoachTeam()) {
            self::applyTrScope($query, $user);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * 목록 whereHas(institution)로 빠지는 *SK 코드 교사도, 해석된 기관 TR이 맞으면 수정 허용.
     */
    public static function allowsWrite(Teacher $teacher, User $user): bool
    {
        if ($user->hasFullAccess()) {
            return true;
        }

        $query = Teacher::query()->where('ID', $teacher->ID);
        self::apply($query, $user);

        if ($query->exists()) {
            return true;
        }

        $hiddenSkCodes = self::hiddenInstitutionSkCodes();
        $candidates = SkCodeNormalizer::candidates((string) ($teacher->SK_Code ?? ''));

        if ($hiddenSkCodes !== [] && array_intersect($candidates, $hiddenSkCodes) !== []) {
            return false;
        }

        if (TeamMenuContext::hasExpandedReadScope($user) || TeamMenuContext::hasAdminMenuDataScope($user)) {
            return true;
        }

        if (! $user->isCoachTeam()) {
            return false;
        }

        $aliases = self::resolveTrAliases($user);
        if ($aliases === []) {
            return false;
        }

        $institution = InstitutionResolver::resolveForTeacher($teacher);
        $tr = ManagerNameNormalizer::normalize((string) ($institution?->accountInfo?->TR ?? ''));

        return $tr !== '' && in_array($tr, $aliases, true);
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    public static function applyTrScope(Builder $query, User $user): void
    {
        $aliases = self::resolveTrAliases($user);

        if ($aliases === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $sqlNormalizedTr = ManagerNameNormalizer::sqlColumnExpression('TR');

        $query->whereHas('institution.accountInfo', function (Builder $sub) use ($aliases, $sqlNormalizedTr): void {
            $sub->where(function (Builder $trQuery) use ($aliases, $sqlNormalizedTr): void {
                foreach ($aliases as $alias) {
                    $trQuery->orWhereRaw("{$sqlNormalizedTr} = ?", [$alias]);
                }
            });
        });
    }

    /**
     * @return array<int, string>
     */
    public static function resolveTrAliases(User $user): array
    {
        $aliases = collect([
            (string) ($user->name ?? ''),
            (string) ($user->email ?? ''),
        ]);

        if (method_exists($user, 'nameForCoReports')) {
            $reportName = $user->nameForCoReports();
            if (filled($reportName)) {
                $aliases->push($reportName);
            }
        }

        if (Schema::hasTable('employee')) {
            $employee = Employee::query()
                ->where('EMAIL', (string) ($user->email ?? ''))
                ->first(['KOREANAME', 'ENGLISHNAME', 'EMAIL']);

            if ($employee) {
                $aliases = $aliases->merge([
                    (string) ($employee->KOREANAME ?? ''),
                    (string) ($employee->ENGLISHNAME ?? ''),
                    (string) ($employee->EMAIL ?? ''),
                ]);
            }
        }

        return $aliases
            ->map(fn (string $value): string => ManagerNameNormalizer::normalize($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    public static function excludeHiddenInstitutions(Builder $query): void
    {
        $hiddenSkCodes = self::hiddenInstitutionSkCodes();

        if ($hiddenSkCodes !== []) {
            $table = $query->getModel()->getTable();
            $query->whereNotIn("{$table}.SK_Code", $hiddenSkCodes);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function hiddenInstitutionSkCodes(): array
    {
        if (! Schema::hasTable('institution_visibility_overrides')) {
            return [];
        }

        return DB::table('institution_visibility_overrides')
            ->whereNotNull('hidden_at')
            ->pluck('sk_code')
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return ['institution.accountInfo'];
    }
}
