<?php

namespace App\Support;

use App\DataTransferObjects\InstitutionListFilters;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InstitutionAccountListQuery
{
    private const DEPT_CO = 'A02';

    private const DEPT_TR = 'A05';

    private const DEPT_CS = 'A03';

    /**
     * @return array<int, string>
     */
    public function hiddenInstitutionSkCodes(): array
    {
        if (! Schema::hasTable('institution_visibility_overrides')) {
            return [];
        }

        return DB::table('institution_visibility_overrides')
            ->whereNotNull('hidden_at')
            ->pluck('sk_code')
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function accountInformationEagerLoads(): array
    {
        $loads = ['institution'];
        if (Schema::hasTable('S_GSNumber')) {
            $loads[] = 'institution.gsNumber';
        }

        return $loads;
    }

    public function accountInformationSummaryQuery(InstitutionListFilters $filters): Builder
    {
        $hiddenInstitutionSkCodes = $this->hiddenInstitutionSkCodes();

        return InstitutionCatalog::query()
            ->when($hiddenInstitutionSkCodes !== [], function (Builder $query) use ($hiddenInstitutionSkCodes): void {
                $query->whereNotIn('SK_Code', $hiddenInstitutionSkCodes);
            })
            ->tap(fn (Builder $query) => $this->applyStatusFilterOnAccountInformation($query, $filters))
            ->tap(fn (Builder $query) => $this->applyTeamAccountInformationScope($query));
    }

    public function accountInformationListQuery(InstitutionListFilters $filters): Builder
    {
        $assignmentColumn = $this->currentUserManagerColumn() ?? 'CO';

        return $this->accountInformationSummaryQuery($filters)
            ->search($filters->search)
            ->when($filters->assignmentFilter === 'assigned', function (Builder $query) use ($assignmentColumn): void {
                $this->applyManagerAssignedConstraint($query, $assignmentColumn);
            })
            ->when($filters->assignmentFilter === 'unassigned', function (Builder $query) use ($assignmentColumn): void {
                $query->where(function (Builder $unassignedQuery) use ($assignmentColumn): void {
                    $unassignedQuery->whereNull($assignmentColumn)
                        ->orWhere($assignmentColumn, '');
                });
            })
            ->when($filters->assignmentFilter === 'my_assigned', function (Builder $query): void {
                $this->applyCurrentUserManagerScopeOnAccountInformation($query);
            })
            ->when(filled($filters->filterCo), function (Builder $query) use ($filters): void {
                $this->applyManagerColumnFilter($query, 'CO', $filters->filterCo, self::DEPT_CO);
            })
            ->when(filled($filters->filterTr), function (Builder $query) use ($filters): void {
                $this->applyManagerColumnFilter($query, 'TR', $filters->filterTr, self::DEPT_TR);
            })
            ->when(filled($filters->filterCs), function (Builder $query) use ($filters): void {
                $this->applyManagerColumnFilter($query, 'CS', $filters->filterCs, self::DEPT_CS);
            });
    }

    public function paginate(InstitutionListFilters $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->accountInformationListQuery($filters)
            ->with($this->accountInformationEagerLoads())
            ->tap(fn (Builder $query) => $this->applyAccountInformationListSort($query, $filters))
            ->paginate($perPage);
    }

    public function listQueryForExport(InstitutionListFilters $filters): Builder
    {
        return $this->accountInformationListQuery($filters)
            ->with($this->accountInformationEagerLoads())
            ->tap(fn (Builder $query) => $this->applyAccountInformationListSort($query, $filters));
    }

    public function statusScopeLabel(InstitutionListFilters $filters): string
    {
        return match ($filters->statusFilter) {
            'terminated' => '해지 기관',
            'active' => '운영 기관',
            default => '전체 기관',
        };
    }

    public function applyStatusFilter(Builder $query, InstitutionListFilters $filters): void
    {
        if ($filters->statusFilter === 'all') {
            return;
        }

        if ($filters->statusFilter === 'terminated') {
            $query->whereHas('accountInfo', function (Builder $sub): void {
                $sub->where('Customer_Type', 'like', '%해지%');
            });

            return;
        }

        $query->where(function (Builder $statusQuery): void {
            $statusQuery->whereDoesntHave('accountInfo')
                ->orWhereHas('accountInfo', function (Builder $sub): void {
                    $sub->where(function (Builder $customerTypeQuery): void {
                        $customerTypeQuery->whereNull('Customer_Type')
                            ->orWhere('Customer_Type', '')
                            ->orWhere('Customer_Type', 'not like', '%해지%');
                    });
                });
        });
    }

    public function applyStatusFilterOnAccountInformation(Builder $query, InstitutionListFilters $filters): void
    {
        if ($filters->statusFilter === 'all') {
            return;
        }

        if ($filters->statusFilter === 'terminated') {
            $query->terminatedCustomers();

            return;
        }

        $query->activeCustomers();
    }

    public function applyTeamInstitutionScope(Builder $query): void
    {
        if (! $this->shouldScopeToAssignedInstitutions()) {
            return;
        }

        $this->applyCurrentUserManagerScope($query);
    }

    public function applyTeamAccountInformationScope(Builder $query): void
    {
        if (! $this->shouldScopeToAssignedInstitutions()) {
            return;
        }

        $this->applyCurrentUserManagerScopeOnAccountInformation($query);
    }

    public function applyCurrentUserManagerScope(Builder $query): void
    {
        $aliases = $this->resolveCurrentUserManagerAliases();
        if ($aliases === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = $this->currentUserManagerColumn();
        if ($column === null) {
            $query->whereHas('accountInfo', function (Builder $sub) use ($aliases): void {
                $sub->where(function (Builder $inner) use ($aliases): void {
                    foreach (['CO', 'TR', 'CS'] as $managerColumn) {
                        $sql = ManagerNameNormalizer::sqlColumnExpression($managerColumn);
                        $inner->orWhere(function (Builder $columnQuery) use ($aliases, $sql): void {
                            foreach ($aliases as $alias) {
                                $columnQuery->orWhereRaw("{$sql} = ?", [$alias]);
                            }
                        });
                    }
                });
            });

            return;
        }

        $sqlNormalized = ManagerNameNormalizer::sqlColumnExpression($column);

        $query->whereHas('accountInfo', function (Builder $sub) use ($aliases, $sqlNormalized): void {
            $sub->where(function (Builder $managerQuery) use ($aliases, $sqlNormalized): void {
                foreach ($aliases as $alias) {
                    $managerQuery->orWhereRaw("{$sqlNormalized} = ?", [$alias]);
                }
            });
        });
    }

    public function applyCurrentUserManagerScopeOnAccountInformation(Builder $query): void
    {
        $aliases = $this->resolveCurrentUserManagerAliases();
        if ($aliases === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = $this->currentUserManagerColumn();
        if ($column === null) {
            $query->where(function (Builder $inner) use ($aliases): void {
                foreach (['CO', 'TR', 'CS'] as $managerColumn) {
                    $sql = ManagerNameNormalizer::sqlColumnExpression($managerColumn);
                    $inner->orWhere(function (Builder $columnQuery) use ($aliases, $sql): void {
                        foreach ($aliases as $alias) {
                            $columnQuery->orWhereRaw("{$sql} = ?", [$alias]);
                        }
                    });
                }
            });

            return;
        }

        $query->whereManagerMatches($column, $aliases);
    }

    public function applyManagerAssignedConstraint(Builder $query, string $column): void
    {
        $query->whereNotNull($column)
            ->where($column, '!=', '');
    }

    public function applyManagerColumnFilter(Builder $query, string $column, string $managerName, string $deptNo): void
    {
        if (blank($managerName)) {
            return;
        }

        $query->whereManagerMatches($column, $this->resolveSelectedManagerAliases($managerName, $deptNo));
    }

    /**
     * @return array<int, string>
     */
    public function resolveSelectedManagerAliases(string $managerName, string $deptNo): array
    {
        $aliases = collect([$managerName]);

        if (Schema::hasTable('employee')) {
            $targetKey = ManagerNameNormalizer::normalize($managerName);

            $employee = Employee::query()
                ->where('WORKDEPT', $deptNo)
                ->where('STATUS', 1)
                ->get(['KOREANAME', 'ENGLISHNAME'])
                ->first(function (Employee $employee) use ($targetKey): bool {
                    return ManagerNameNormalizer::normalize((string) ($employee->ENGLISHNAME ?? '')) === $targetKey
                        || ManagerNameNormalizer::normalize((string) ($employee->KOREANAME ?? '')) === $targetKey;
                });

            if ($employee !== null) {
                $aliases->push(
                    (string) ($employee->ENGLISHNAME ?? ''),
                    (string) ($employee->KOREANAME ?? ''),
                );
            }
        }

        return $aliases
            ->map(fn (string $value): string => ManagerNameNormalizer::normalize($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function applyAccountInformationListSort(Builder $query, InstitutionListFilters $filters): void
    {
        $direction = $filters->sortDirection === 'desc' ? 'desc' : 'asc';

        if ($filters->sortField === 'FGC_CreateDate' && Schema::hasColumn('S_Account_Information', 'FGC_CreateDate')) {
            $nullsOrder = $direction === 'asc' ? 'ASC' : 'DESC';
            $query->orderByRaw("FGC_CreateDate IS NULL {$nullsOrder}")
                ->orderBy('FGC_CreateDate', $direction);

            if (Schema::hasColumn('S_Account_Information', 'ID')) {
                $query->orderBy('ID', $direction);
            }

            return;
        }

        if ($filters->sortField === 'AccountName') {
            $query->orderBy('Account_Name', $direction)
                ->orderBy('SK_Code');

            return;
        }

        if ($filters->sortField === 'SKcode') {
            $query->orderBy('SK_Code', $direction);

            return;
        }

        $sortableOnMaster = ['GSno', 'Gubun', 'Director', 'Phone', 'AccountTel'];
        if (in_array($filters->sortField, $sortableOnMaster, true)) {
            $field = $filters->sortField;
            $query->leftJoin('S_AccountName', 'S_Account_Information.SK_Code', '=', 'S_AccountName.SKcode')
                ->orderBy("S_AccountName.{$field}", $direction)
                ->select('S_Account_Information.*');

            return;
        }

        if (Schema::hasColumn('S_Account_Information', 'FGC_CreateDate')) {
            $nullsOrder = $direction === 'asc' ? 'ASC' : 'DESC';
            $query->orderByRaw("FGC_CreateDate IS NULL {$nullsOrder}")
                ->orderBy('FGC_CreateDate', $direction);

            if (Schema::hasColumn('S_Account_Information', 'ID')) {
                $query->orderBy('ID', $direction);
            }

            return;
        }

        $query->orderBy('SK_Code', $direction);
    }

    public function shouldScopeToAssignedInstitutions(): bool
    {
        $user = auth()->user();
        if (! $user || TeamMenuContext::hasExpandedReadScope($user)) {
            return false;
        }

        return $user->isCoTeam() || $user->isCoachTeam() || $user->isCsTeam();
    }

    public function currentUserManagerColumn(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        if ($user->isCoTeam()) {
            return 'CO';
        }

        if ($user->isCoachTeam()) {
            return 'TR';
        }

        if ($user->isCsTeam()) {
            return 'CS';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function resolveCurrentUserManagerAliases(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $aliases = collect([
            (string) ($user->name ?? ''),
            (string) ($user->email ?? ''),
        ]);

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
            ->map(fn (string $value): string => $this->normalizeManagerAlias($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function normalizeManagerAlias(string $value): string
    {
        return ManagerNameNormalizer::normalize($value);
    }
}
