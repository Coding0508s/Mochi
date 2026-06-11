<?php

namespace App\Support;

use App\Models\User;

/**
 * Setup 역할의 메뉴 권한 — SetUp 화면만 역할로 위임합니다.
 * People·기관·연락처·지원·잠재기관은 로그인 + 팀 스코프가 기본이며 여기서 다루지 않습니다.
 */
final class SetupRolePermissions
{
    public const MENU_SETUP = 'setup';

    public const ACTION_VIEW = 'view';

    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_DELETE = 'delete';

    /**
     * @return list<string>
     */
    public static function menus(): array
    {
        return [self::MENU_SETUP];
    }

    /**
     * @return list<string>
     */
    public static function actions(): array
    {
        return [
            self::ACTION_VIEW,
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
        ];
    }

    /**
     * Gate ability → [menu, action]
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function abilityMap(): array
    {
        return [
            'accessSetup' => [self::MENU_SETUP, self::ACTION_VIEW],
            'manageTeamStructure' => [self::MENU_SETUP, self::ACTION_UPDATE],
            'deleteTeamStructure' => [self::MENU_SETUP, self::ACTION_DELETE],
        ];
    }

    public static function allowsAbility(?User $user, string $ability): ?bool
    {
        if ($user === null) {
            return false;
        }

        $map = self::abilityMap()[$ability] ?? null;
        if ($map === null) {
            return null;
        }

        return self::allows($user, $map[0], $map[1]);
    }

    public static function allows(User $user, string $menu, string $action): bool
    {
        if ($user->hasFullAccess()) {
            return true;
        }

        if ($user->isDeputyAdmin()) {
            return $menu === self::MENU_SETUP && $action === self::ACTION_VIEW;
        }

        return $user->rolePermission($menu, $action);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function defaultMatrix(): array
    {
        $matrix = [];

        foreach (self::menus() as $menu) {
            $matrix[$menu] = array_fill_keys(self::actions(), false);
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>|null  $saved
     * @return array<string, array<string, bool>>
     */
    public static function normalizeMatrix(?array $saved): array
    {
        $normalized = self::defaultMatrix();

        if (! is_array($saved)) {
            return $normalized;
        }

        foreach ($normalized as $menu => $actions) {
            foreach (array_keys($actions) as $action) {
                $normalized[$menu][$action] = (bool) ($saved[$menu][$action] ?? false);
            }
        }

        return $normalized;
    }
}
