<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\User;
use App\Support\TeamMenuContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamMenuContextTest extends TestCase
{
    #[DataProvider('inferTeamProvider')]
    public function test_infer_user_team_from_job(string $job, ?string $expected): void
    {
        $this->assertSame($expected, TeamMenuContext::inferUserTeamFromJob($job));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function inferTeamProvider(): array
    {
        return [
            'coach job' => ['Coach', 'COACH'],
            'tr job' => ['TR', 'COACH'],
            'cs job' => ['CS', 'CS'],
            'country manager' => ['CountryManager', 'CO'],
            'empty' => ['', null],
        ];
    }

    public function test_coach_user_cannot_access_co_only_features(): void
    {
        $coach = new User(['team' => 'COACH', 'is_admin' => false]);

        $this->assertFalse(TeamMenuContext::showCoTeamSidebar($coach));
        $this->assertFalse(TeamMenuContext::canAccessCoOnlyFeatures($coach));
    }

    public function test_co_user_can_access_co_features(): void
    {
        $co = new User(['team' => 'CO', 'is_admin' => false]);

        $this->assertTrue(TeamMenuContext::showCoTeamSidebar($co));
    }

    public function test_admin_can_access_co_features(): void
    {
        $admin = new User(['team' => 'COACH', 'is_admin' => true]);

        $this->assertTrue(TeamMenuContext::showCoTeamSidebar($admin));
    }

    public function test_show_all_team_sidebars_for_authenticated_user(): void
    {
        $coach = new User(['team' => 'COACH', 'is_admin' => false]);

        $this->assertTrue(TeamMenuContext::showAllTeamSidebars($coach));
    }

    public function test_cross_team_read_only_when_menu_differs_from_home_team(): void
    {
        $coach = new User(['team' => 'COACH', 'is_admin' => false]);

        $this->assertTrue(
            TeamMenuContext::isCrossTeamReadOnlyContext($coach, TeamMenuContext::MENU_CO)
        );
        $this->assertFalse(
            TeamMenuContext::isCrossTeamReadOnlyContext($coach, TeamMenuContext::MENU_COACH)
        );
    }

    public function test_coach_user_sees_exclusive_coach_sidebar_only(): void
    {
        $coach = new User(['team' => 'COACH', 'is_admin' => false]);

        $this->assertFalse(TeamMenuContext::showMultiTeamSidebar($coach));
        $this->assertTrue(TeamMenuContext::showExclusiveCoachSidebar($coach));
        $this->assertFalse(TeamMenuContext::showExclusiveCsSidebar($coach));
    }

    public function test_co_user_sees_co_sidebar_not_multi_team_switcher(): void
    {
        $co = new User(['team' => 'CO', 'is_admin' => false]);

        $this->assertFalse(TeamMenuContext::showMultiTeamSidebar($co));
        $this->assertFalse(TeamMenuContext::showExclusiveCoachSidebar($co));
        $this->assertTrue(TeamMenuContext::showCoTeamSidebar($co));
    }

    public function test_workdept_a05_overrides_wrong_users_team_for_menu(): void
    {
        $user = new User([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'TEST01',
        ]);
        $user->setRelation('employee', new Employee([
            'EMPNO' => 'TEST01',
            'WORKDEPT' => 'A05',
        ]));

        $this->assertSame('COACH', TeamMenuContext::resolveTeamCode($user));
        $this->assertTrue(TeamMenuContext::showExclusiveCoachSidebar($user));
        $this->assertFalse(TeamMenuContext::showCoTeamSidebar($user));
    }

    public function test_administration_team_member_can_see_admin_sidebar(): void
    {
        $user = new User([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM001',
        ]);
        $user->setRelation('employee', new Employee([
            'EMPNO' => 'ADM001',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
        ]));

        $this->assertTrue(TeamMenuContext::isAdministrationTeam($user));
        $this->assertTrue(TeamMenuContext::showAdminTeamSidebar($user));
    }

    public function test_non_administration_team_member_cannot_see_admin_sidebar(): void
    {
        $user = new User([
            'team' => 'COACH',
            'is_admin' => false,
            'employee_empno' => 'COACH01',
        ]);
        $user->setRelation('employee', new Employee([
            'EMPNO' => 'COACH01',
            'WORKDEPT' => TeamMenuContext::DEPT_COACH,
        ]));

        $this->assertFalse(TeamMenuContext::isAdministrationTeam($user));
        $this->assertFalse(TeamMenuContext::showAdminTeamSidebar($user));
    }

    public function test_administration_team_in_admin_sidebar_context_is_not_cross_team_read_only(): void
    {
        $user = new User([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM001',
        ]);
        $user->setRelation('employee', new Employee([
            'EMPNO' => 'ADM001',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
        ]));

        $this->get('/coach/retired-teachers?sidebar_context=admin&team_menu=coach');

        $this->assertFalse(
            TeamMenuContext::isCrossTeamReadOnlyContext($user, TeamMenuContext::MENU_COACH)
        );
    }

    public function test_infer_user_team_for_registration_prefers_workdept(): void
    {
        $this->assertSame(
            'COACH',
            TeamMenuContext::inferUserTeamForRegistration('A05', 'TEST')
        );
    }

    public function test_institution_support_report_uses_coach_team_label_for_coach_menu(): void
    {
        $this->get('/supports/create?team_menu=coach');

        $this->assertSame('Coach Team', TeamMenuContext::institutionSupportReportBrand());
        $this->assertSame(
            'Coach Team 기관지원보고서 작성',
            TeamMenuContext::institutionSupportReportFormHeading()
        );
        $this->assertSame(
            'Coach Team 교사지원보고서 작성',
            TeamMenuContext::supportReportFormHeading(reportMode: 'teacher')
        );
        $this->assertSame(
            'Coach Team 기관 지원 보고서',
            TeamMenuContext::institutionSupportReportMailOpening()
        );
        $this->assertSame(
            '[Coach Team 교사 지원 보고서]',
            TeamMenuContext::teacherSupportReportMailSubjectPrefix()
        );
    }

    public function test_teacher_support_report_mail_uses_coach_labels_for_coach_menu(): void
    {
        $this->get('/supports/create?team_menu=coach');

        $this->assertSame(
            'Coach Team 교사 지원 보고서',
            TeamMenuContext::teacherSupportReportMailOpening()
        );
        $this->assertSame(
            '담당 Coach',
            TeamMenuContext::institutionSupportReportAssigneeLabel()
        );
    }

    public function test_institution_support_report_uses_co_label_for_co_menu(): void
    {
        $this->get('/supports/create?team_menu=co');

        $this->assertSame('CO', TeamMenuContext::institutionSupportReportBrand());
        $this->assertSame('CO 기관지원보고서 작성', TeamMenuContext::institutionSupportReportFormHeading());
        $this->assertSame('CO 기관 지원 보고서', TeamMenuContext::institutionSupportReportMailOpening());
    }

    public function test_coach_workdept_user_gets_coach_team_mail_labels_without_query(): void
    {
        $user = new User([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'TEST01',
        ]);
        $user->setRelation('employee', new Employee([
            'EMPNO' => 'TEST01',
            'WORKDEPT' => 'A05',
        ]));

        $this->assertSame('Coach Team', TeamMenuContext::institutionSupportReportBrand($user));
        $this->assertSame('담당 Coach', TeamMenuContext::institutionSupportReportAssigneeLabel($user));
        $this->assertSame('Coach', TeamMenuContext::institutionSupportReportMailAssigneeColumnLabel($user));
    }
}
