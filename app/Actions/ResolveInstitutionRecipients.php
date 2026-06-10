<?php

namespace App\Actions;

use App\Models\AccountInformation;
use App\Models\User;
use App\Support\ManagerNameNormalizer;
use Illuminate\Support\Collection;

class ResolveInstitutionRecipients
{
    /**
     * @return Collection<int, array{id:int, name:string, email:?string, roles:list<string>}>
     */
    public function execute(string $skCode): Collection
    {
        $trimmedSkCode = trim($skCode);
        if ($trimmedSkCode === '') {
            return collect();
        }

        $accountInfo = AccountInformation::query()
            ->where('SK_Code', $trimmedSkCode)
            ->first();

        if ($accountInfo === null) {
            return collect();
        }

        $managerNamesByRole = collect([
            'CO' => (string) ($accountInfo->CO ?? ''),
            'TR' => (string) ($accountInfo->TR ?? ''),
            'CS' => (string) ($accountInfo->CS ?? ''),
        ])->map(fn (string $name): string => trim($name))
            ->filter(fn (string $name): bool => $name !== '');

        if ($managerNamesByRole->isEmpty()) {
            return collect();
        }

        /** @var Collection<string, list<string>> $normalizedRoleMap */
        $normalizedRoleMap = collect();
        foreach ($managerNamesByRole as $role => $managerName) {
            $normalized = ManagerNameNormalizer::normalize($managerName);
            if ($normalized === '') {
                continue;
            }

            $existingRoles = $normalizedRoleMap->get($normalized, []);
            $existingRoles[] = (string) $role;
            $normalizedRoleMap->put($normalized, array_values(array_unique($existingRoles)));
        }

        if ($normalizedRoleMap->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->where('is_active', true)
            ->with('employee')
            ->get(['id', 'name', 'email', 'employee_empno']);

        return $users
            ->map(function (User $user) use ($normalizedRoleMap): ?array {
                $candidates = array_values(array_unique(array_filter([
                    ManagerNameNormalizer::normalize((string) $user->name),
                    ManagerNameNormalizer::normalize($user->nameForCoReports()),
                    ManagerNameNormalizer::normalize((string) ($user->employee?->KOREANAME ?? '')),
                    ManagerNameNormalizer::normalize((string) ($user->employee?->ENGLISHNAME ?? '')),
                ])));

                $roles = collect($candidates)
                    ->flatMap(fn (string $key): array => $normalizedRoleMap->get($key, []))
                    ->unique()
                    ->values()
                    ->all();

                if ($roles === []) {
                    return null;
                }

                return [
                    'id' => (int) $user->id,
                    'name' => $user->preferredDisplayName(),
                    'email' => filled($user->email) ? (string) $user->email : null,
                    'roles' => $roles,
                ];
            })
            ->filter()
            ->values();
    }
}
