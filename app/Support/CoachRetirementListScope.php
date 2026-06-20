<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Coach 퇴직교사 리스트 TR / hidden 기관 스코프.
 */
final class CoachRetirementListScope
{
    /**
     * @param  Builder<Model>  $query
     */
    public static function apply(Builder $query, ?User $user = null): void
    {
        $user ??= auth()->user();
        $table = $query->getModel()->getTable();
        $skCodeColumn = self::qualifiedSkCodeColumn($table);

        $hiddenSkCodes = CoachTeacherScope::hiddenInstitutionSkCodes();
        if ($hiddenSkCodes !== [] && $skCodeColumn !== null) {
            $query->whereNotIn($skCodeColumn, $hiddenSkCodes);
        }

        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (TeamMenuContext::hasExpandedReadScope($user)) {
            return;
        }

        if (! $user->isCoachTeam()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $aliases = CoachTeacherScope::resolveTrAliases($user);
        if ($aliases === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $trColumn = self::qualifiedTrColumn($table);
        if ($trColumn !== null) {
            $sqlNormalizedTr = ManagerNameNormalizer::sqlColumnExpression($trColumn);
            $query->where(function (Builder $trQuery) use ($aliases, $sqlNormalizedTr): void {
                foreach ($aliases as $alias) {
                    $trQuery->orWhereRaw("{$sqlNormalizedTr} = ?", [$alias]);
                }
            });

            return;
        }

        if ($skCodeColumn === null || ! Schema::hasTable('S_Account_Information')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($skCodeColumn, function ($subQuery) use ($aliases): void {
            $trColumnExpression = ManagerNameNormalizer::sqlColumnExpression('TR');

            $subQuery->select('SK_Code')
                ->from('S_Account_Information')
                ->where(function ($trQuery) use ($aliases, $trColumnExpression): void {
                    foreach ($aliases as $alias) {
                        $trQuery->orWhereRaw("{$trColumnExpression} = ?", [$alias]);
                    }
                });
        });
    }

    private static function qualifiedSkCodeColumn(string $table): ?string
    {
        $columns = config('coach_retired_teachers.teacher_master.columns', []);
        $candidateColumns = array_unique(array_filter([
            $columns['sk_code'] ?? null,
            config('coach_retired_teachers.columns.sk_code'),
        ]));

        foreach ($candidateColumns as $column) {
            if (is_string($column) && $column !== '' && Schema::hasColumn($table, $column)) {
                return "{$table}.{$column}";
            }
        }

        return null;
    }

    private static function qualifiedTrColumn(string $table): ?string
    {
        $columns = config('coach_retired_teachers.teacher_master.columns', []);
        $candidateColumns = array_unique(array_filter([
            $columns['tr_name'] ?? null,
            config('coach_retired_teachers.columns.tr_name'),
        ]));

        foreach ($candidateColumns as $column) {
            if (is_string($column) && $column !== '' && Schema::hasColumn($table, $column)) {
                return "{$table}.{$column}";
            }
        }

        return null;
    }
}
