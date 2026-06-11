<?php

namespace App\Support;

use App\Models\User;

class SetupRoleAccountFlags
{
    public const FLAG_IS_ADMIN = 'is_admin';

    public const FLAG_IS_DEPUTY_ADMIN = 'is_deputy_admin';

    public const FLAG_IS_GS_BROCHURE_ADMIN = 'is_gs_brochure_admin';

    public const FLAG_CAN_MANAGE_STORE_INVENTORY = 'can_manage_store_inventory';

    public const FLAG_IS_COACH_TEAM_LEAD = 'is_coach_team_lead';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::FLAG_IS_ADMIN,
            self::FLAG_IS_DEPUTY_ADMIN,
            self::FLAG_IS_GS_BROCHURE_ADMIN,
            self::FLAG_CAN_MANAGE_STORE_INVENTORY,
            self::FLAG_IS_COACH_TEAM_LEAD,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return array_fill_keys(self::keys(), false);
    }

    /**
     * @param  array<string, mixed>|null  $flags
     * @return array<string, bool>
     */
    public static function normalize(?array $flags): array
    {
        $normalized = self::defaults();

        foreach (self::keys() as $key) {
            $normalized[$key] = (bool) ($flags[$key] ?? false);
        }

        if ($normalized[self::FLAG_IS_ADMIN]) {
            $normalized[self::FLAG_IS_DEPUTY_ADMIN] = false;
            $normalized[self::FLAG_IS_COACH_TEAM_LEAD] = false;
        }

        if ($normalized[self::FLAG_IS_DEPUTY_ADMIN]) {
            $normalized[self::FLAG_IS_ADMIN] = false;
            $normalized[self::FLAG_IS_COACH_TEAM_LEAD] = false;
        }

        return $normalized;
    }

    /**
     * @param  array<string, bool>  $flags
     */
    public static function applyToUser(User $user, array $flags): void
    {
        $normalized = self::normalize($flags);

        $user->forceFill([
            'is_admin' => $normalized[self::FLAG_IS_ADMIN],
            'is_deputy_admin' => $normalized[self::FLAG_IS_DEPUTY_ADMIN],
            'is_gs_brochure_admin' => $normalized[self::FLAG_IS_GS_BROCHURE_ADMIN],
            'can_manage_store_inventory' => $normalized[self::FLAG_CAN_MANAGE_STORE_INVENTORY],
            'is_coach_team_lead' => $normalized[self::FLAG_IS_COACH_TEAM_LEAD],
        ]);
    }
}
