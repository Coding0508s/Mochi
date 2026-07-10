<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Teams 사이드바(team_menu) 및 CO 전용 기능 접근.
 * 부서 코드(A01/A02/A03/A05)는 Setup 팀 관리·InstitutionList 담당자 드롭다운과 동일.
 */
final class TeamMenuContext
{
    public const MENU_CS = 'cs';

    public const MENU_COACH = 'coach';

    public const MENU_CO = 'co';

    public const MENU_LOGISTICS = 'logistics';

    public const DEPT_ADMINISTRATION = 'A01';

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
        if (in_array($fromQuery, [self::MENU_CS, self::MENU_COACH, self::MENU_CO, self::MENU_LOGISTICS], true)) {
            return $fromQuery;
        }

        $user ??= auth()->user();
        $team = self::resolveTeamCode($user);

        return self::menuForTeamCode($team);
    }

    /**
     * @return self::MENU_*|null
     */
    public static function menuForTeamCode(string $teamCode): ?string
    {
        return match (mb_strtoupper(trim($teamCode))) {
            'CS' => self::MENU_CS,
            'COACH', 'TR', 'TRAINING' => self::MENU_COACH,
            'CO' => self::MENU_CO,
            default => null,
        };
    }

    /**
     * team_menu 컨텍스트가 본인 소속 팀과 다르면 조회 전용.
     * Full Access(관리자)는 모든 컨텍스트에서 편집 가능.
     */
    public static function isCrossTeamReadOnlyContext(?User $user, ?string $teamMenuOverride = null): bool
    {
        if ($user === null || $user->hasFullAccess()) {
            return false;
        }

        if (self::isAdministrationTeam($user) && self::currentSidebarContext() === 'admin') {
            return false;
        }

        $activeMenu = self::normalizeTeamMenu($teamMenuOverride) ?? self::activeMenu($user);
        if ($activeMenu === null || $activeMenu === self::MENU_LOGISTICS) {
            return false;
        }

        $homeTeamCode = self::resolveTeamCode($user);
        if ($homeTeamCode === '') {
            return false;
        }

        $homeMenu = self::menuForTeamCode($homeTeamCode);
        if ($homeMenu === null) {
            return false;
        }

        return $activeMenu !== $homeMenu;
    }

    /**
     * 타 팀 메뉴 조회 시 관리자급 READ 스코프(WRITE는 별도 차단).
     */
    public static function hasExpandedReadScope(?User $user, ?string $teamMenuOverride = null): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasPlatformWideViewAccess()
            || self::isCrossTeamReadOnlyContext($user, $teamMenuOverride);
    }

    public static function abortIfCrossTeamReadOnly(?User $user = null, ?string $teamMenuOverride = null): void
    {
        $user ??= auth()->user();

        if (self::isCrossTeamReadOnlyContext($user, $teamMenuOverride)) {
            throw new AuthorizationException('다른 팀 메뉴에서는 조회만 가능합니다.');
        }
    }

    /**
     * Teams 사이드바: 로그인 사용자에게 CS·Coach·CO 블록 모두 노출.
     */
    public static function showAllTeamSidebars(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Admin 사이드바(`sidebar_context=admin`)에서 Administration Team 전체 데이터 조회.
     */
    public static function hasAdminMenuDataScope(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return self::isAdministrationTeam($user)
            && self::currentSidebarContext() === 'admin';
    }

    /**
     * Administration Team(A01) 소속 여부 — employee.WORKDEPT 기준.
     */
    public static function isAdministrationTeam(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $user->loadMissing('employee');

        return mb_strtoupper(trim((string) ($user->employee?->WORKDEPT ?? ''))) === self::DEPT_ADMINISTRATION;
    }

    /**
     * Teams 사이드바 Admin 메뉴(퇴직교사 리스트) 노출 — Full Access 또는 Administration Team.
     */
    public static function showAdminTeamSidebar(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasFullAccess() || self::isAdministrationTeam($user);
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
            'CS Team' => '담당 CS',
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

    public static function teacherSupportReportMailOpening(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return match (self::institutionSupportReportBrand($user, $teamMenuOverride)) {
            'Coach Team' => 'Coach Team 교사 지원 보고서',
            'CS Team' => 'CS Team 교사 지원 보고서',
            'CO' => 'CO 교사 지원 보고서',
            default => '교사 지원 보고서',
        };
    }

    public static function teacherSupportReportMailSubjectPrefix(?User $user = null, ?string $teamMenuOverride = null): string
    {
        return match (self::institutionSupportReportBrand($user, $teamMenuOverride)) {
            'Coach Team' => '[Coach Team 교사 지원 보고서]',
            'CS Team' => '[CS Team 교사 지원 보고서]',
            'CO' => '[CO 교사 지원 보고서]',
            default => '[교사 지원 보고서]',
        };
    }

    /**
     * @return self::MENU_*|null
     */
    private static function normalizeTeamMenu(?string $teamMenu): ?string
    {
        return in_array($teamMenu, [self::MENU_CS, self::MENU_COACH, self::MENU_CO, self::MENU_LOGISTICS], true)
            ? $teamMenu
            : null;
    }

    private static function currentSidebarContext(): string
    {
        $fromQuery = trim((string) request()->query('sidebar_context', ''));
        if ($fromQuery !== '') {
            return $fromQuery;
        }

        $fromSession = trim((string) session('sidebar_context', ''));
        if ($fromSession !== '') {
            return $fromSession;
        }

        $referer = trim((string) request()->headers->get('referer', ''));
        if ($referer === '') {
            return '';
        }

        $query = parse_url($referer, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $queryParams);

        return trim((string) ($queryParams['sidebar_context'] ?? ''));
    }
}
