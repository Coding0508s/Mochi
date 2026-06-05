<?php

namespace App\Support;

use App\Models\User;

/**
 * Teams 사이드바(team_menu) 및 CO 전용 기능 접근.
 * 부서 코드(A02/A03/A05)는 InstitutionList 담당자 드롭다운과 동일.
 */
final class TeamMenuContext
{
    public const MENU_CS = 'cs';

    public const MENU_COACH = 'coach';

    public const MENU_CO = 'co';

    public const DEPT_CO = 'A02';

    public const DEPT_CS = 'A03';

    public const DEPT_COACH = 'A05';

    /**
     * 로그인 계정 팀 판별: 연결된 employee.WORKDEPT 우선(관리자 제외), 없으면 users.team.
     */
    public static function resolveTeamCode(?User $user): string
    {
        if ($user === null) {
            return '';
        }

        $user->loadMissing('employee');

        $fromAccount = mb_strtoupper(trim((string) $user->team));
        $fromWorkDept = self::inferUserTeamFromWorkDept((string) ($user->employee?->WORKDEPT ?? ''));

        if ($user->hasPlatformWideViewAccess()) {
            return $fromAccount !== '' ? $fromAccount : ($fromWorkDept ?? '');
        }

        if ($fromWorkDept !== null) {
            return $fromWorkDept;
        }

        return $fromAccount;
    }

    public static function inferUserTeamFromWorkDept(string $workDept): ?string
    {
        $code = mb_strtoupper(trim($workDept));

        return match ($code) {
            self::DEPT_COACH => 'COACH',
            self::DEPT_CS => 'CS',
            self::DEPT_CO => 'CO',
            default => null,
        };
    }

    /**
     * @return string|null users.team 저장용
     */
    public static function inferUserTeamForRegistration(string $workDept, string $job): ?string
    {
        return self::inferUserTeamFromWorkDept($workDept)
            ?? self::inferUserTeamFromJob($job);
    }

    /**
     * CO Team 사이드바(잠재기관·Store 등) 노출 여부.
     */
    public static function showCoTeamSidebar(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasPlatformWideViewAccess()) {
            return true;
        }

        $team = self::resolveTeamCode($user);

        if ($team === 'CO') {
            return true;
        }

        if (in_array($team, ['CS', 'COACH', 'TR', 'TRAINING'], true)) {
            return false;
        }

        return $team === '';
    }

    public static function canAccessCoOnlyFeatures(?User $user): bool
    {
        return self::showCoTeamSidebar($user);
    }

    /**
     * 관리자·팀 미지정: CS+Coach 동시 노출. CO/CS/Coach 전용 계정은 false.
     */
    public static function showMultiTeamSidebar(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasPlatformWideViewAccess()) {
            return true;
        }

        $team = self::resolveTeamCode($user);

        return $team === '';
    }

    public static function showExclusiveCoachSidebar(?User $user): bool
    {
        if ($user === null || $user->hasPlatformWideViewAccess()) {
            return false;
        }

        return in_array(self::resolveTeamCode($user), ['COACH', 'TR', 'TRAINING'], true);
    }

    public static function showExclusiveCsSidebar(?User $user): bool
    {
        if ($user === null || $user->hasPlatformWideViewAccess()) {
            return false;
        }

        return self::resolveTeamCode($user) === 'CS';
    }

    public static function activeMenu(?User $user = null): ?string
    {
        $fromQuery = request()->query('team_menu');
        if (in_array($fromQuery, [self::MENU_CS, self::MENU_COACH, self::MENU_CO], true)) {
            return $fromQuery;
        }

        $user ??= auth()->user();
        $team = self::resolveTeamCode($user);

        return match ($team) {
            'CS' => self::MENU_CS,
            'COACH', 'TR', 'TRAINING' => self::MENU_COACH,
            'CO' => self::MENU_CO,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = [], ?User $user = null, ?string $teamMenuOverride = null): string
    {
        $teamMenu = self::normalizeTeamMenu($teamMenuOverride) ?? self::activeMenu($user);
        if ($teamMenu !== null && ! array_key_exists('team_menu', $parameters)) {
            $parameters['team_menu'] = $teamMenu;
        }

        return route($name, $parameters);
    }

    /**
     * 직원 JOB 문자열로 users.team 초기값 추론 (등록 시).
     */
    public static function inferUserTeamFromJob(string $job): ?string
    {
        $normalized = mb_strtoupper(trim($job));

        if ($normalized === '') {
            return null;
        }

        if (
            $normalized === 'COACH'
            || $normalized === 'TR'
            || $normalized === 'TRAINING'
            || str_contains($normalized, 'COACH')
        ) {
            return 'COACH';
        }

        if ($normalized === 'CS' || str_contains($normalized, 'CS')) {
            return 'CS';
        }

        return 'CO';
    }

    /**
     * 기관지원보고서 화면·메일 공통 팀 표기 (예: Coach Team, CO, CS Team).
     *
     * @param  self::MENU_*|null  $teamMenuOverride  Livewire 저장 등 후속 요청용
     */
    public static function institutionSupportReportBrand(?User $user = null, ?string $teamMenuOverride = null): ?string
    {
        $user ??= auth()->user();
        $menu = self::normalizeTeamMenu($teamMenuOverride) ?? self::activeMenu($user);

        return match ($menu) {
            self::MENU_COACH => 'Coach Team',
            self::MENU_CO => 'CO',
            self::MENU_CS => 'CS Team',
            default => match (self::resolveTeamCode($user)) {
                'COACH', 'TR', 'TRAINING' => 'Coach Team',
                'CO' => 'CO',
                'CS' => 'CS Team',
                default => null,
            },
        };
    }

    public static function institutionSupportReportFormHeading(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return self::supportReportFormHeading($user, $teamMenuOverride, 'institution');
    }

    public static function institutionSupportReportFormSubtitle(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return self::supportReportFormSubtitle($user, $teamMenuOverride, 'institution');
    }

    public static function supportReportFormHeading(?User $user = null, ?string $teamMenuOverride = null, string $reportMode = 'institution'): string
    {
        $brand = self::institutionSupportReportBrand($user, $teamMenuOverride) ?? 'CO';

        return $reportMode === 'teacher'
            ? $brand.' 교사지원보고서 작성'
            : $brand.' 기관지원보고서 작성';
    }

    public static function supportReportFormSubtitle(?User $user = null, ?string $teamMenuOverride = null, string $reportMode = 'institution'): string
    {
        $brand = self::institutionSupportReportBrand($user, $teamMenuOverride) ?? 'CO';

        return $reportMode === 'teacher'
            ? $brand.' 교사 지원 보고서'
            : $brand.' 기관 지원 보고서';
    }

    public static function institutionSupportReportAssigneeLabel(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return match (self::institutionSupportReportBrand($user, $teamMenuOverride)) {
            'Coach Team' => '담당 Coach',
            'CS Team' => '담당 CS ',
            'CO' => '담당 CO',
            default => 'CO명',
        };
    }

    public static function institutionSupportReportMailOpening(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return match (self::institutionSupportReportBrand($user, $teamMenuOverride)) {
            'Coach Team' => 'Coach Team 기관 지원 보고서',
            'CS Team' => 'CS Team 기관 지원 보고서',
            'CO' => 'CO 기관 지원 보고서',
            default => '기관 지원 보고서',
        };
    }

    public static function institutionSupportReportMailAssigneeColumnLabel(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return match (self::institutionSupportReportBrand($user, $teamMenuOverride)) {
            'Coach Team' => 'Coach',
            'CS Team' => '담당 CS Team',
            'CO' => '담당 CO',
            default => '담당 CO',
        };
    }

    public static function institutionSupportReportMailSubjectPrefix(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return match (self::institutionSupportReportBrand($user, $teamMenuOverride)) {
            'Coach Team' => '[Coach Team 기관 지원 보고서]',
            'CS Team' => '[CS Team 기관 지원 보고서]',
            'CO' => '[CO 기관 지원 보고서]',
            default => '[기관 지원 보고서]',
        };
    }

    /**
     * @return self::MENU_*|null
     */
    private static function normalizeTeamMenu(?string $teamMenu): ?string
    {
        return in_array($teamMenu, [self::MENU_CS, self::MENU_COACH, self::MENU_CO], true)
            ? $teamMenu
            : null;
    }
}
