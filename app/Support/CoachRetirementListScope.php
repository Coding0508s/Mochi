<?php

namespace App\Support;

use App\Models\RetirementList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coach 퇴직교사 리스트(S_RetirementList) TR / hidden 기관 스코프.
 */
final class CoachRetirementListScope
{
    /**
     * @param  Builder<RetirementList>  $query
     */
    public static function apply(Builder $query, ?User $user = null): void
    {
        $user ??= auth()->user();
        $table = $query->getModel()->getTable();

        $hiddenSkCodes = CoachTeacherScope::hiddenInstitutionSkCodes();
        if ($hiddenSkCodes !== []) {
            $query->whereNotIn("{$table}.SK_Code", $hiddenSkCodes);
        }

        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->hasFullAccess()) {
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

        $sqlNormalizedTr = ManagerNameNormalizer::sqlColumnExpression("{$table}.TR_Name");

        $query->where(function (Builder $trQuery) use ($aliases, $sqlNormalizedTr): void {
            foreach ($aliases as $alias) {
                $trQuery->orWhereRaw("{$sqlNormalizedTr} = ?", [$alias]);
            }
        });
    }
}
