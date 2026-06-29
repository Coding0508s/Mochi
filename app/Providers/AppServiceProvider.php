<?php

namespace App\Providers;

use App\Models\SharedSupply;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\TeamSchedule;
use App\Models\User;
use App\Observers\SharedSupplyObserver;
use App\Policies\SharedSupplyPolicy;
use App\Policies\TeamSchedulePolicy;
use App\Support\InstitutionAccountListQuery;
use App\Support\ManagerNameNormalizer;
use App\Support\TeacherSupportReportEditAuthorization;
use App\Support\TeamMenuContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('external-institution-ingest', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->bearerToken() ?: $request->ip());
        });

        // GS Brochure 레거시 Blade를 통합 앱에서 직접 렌더링하기 위한 뷰 경로 등록
        View::addLocation(base_path('GSBrochure/laravel/resources/views'));

        Gate::define('editEmployeeProfile', fn (?User $user): bool => (bool) ($user?->hasFullAccess()));

        Gate::define('manageEmployeeDepartment', fn (?User $user): bool => (bool) ($user?->hasFullAccess()));

        Gate::define('accessSetup', fn (?User $user): bool => (bool) $user?->canAccessSetup());

        Gate::define('manageTeamStructure', fn (?User $user): bool => (bool) $user?->canManageSetup());

        Gate::define('manageStoreInventory', fn (?User $user): bool => (bool) ($user?->hasFullAccess() || $user?->can_manage_store_inventory));

        Gate::define('manageGsBrochureAdmin', fn (?User $user): bool => (bool) ($user?->hasFullAccess() || $user?->is_gs_brochure_admin));

        Gate::define('viewAdminMenu', fn (?User $user): bool => TeamMenuContext::showAdminTeamSidebar($user));

        Gate::define('manageUserAccounts', fn (?User $user): bool => (bool) ($user?->hasFullAccess()));

        /** Coach Team 지원 KPI 대시보드 — 팀장·관리자 */
        Gate::define('viewCoachTeamKpi', fn (?User $user): bool => (bool) $user?->canViewCoachTeamKpi());
        Gate::policy(TeamSchedule::class, TeamSchedulePolicy::class);
        Gate::policy(SharedSupply::class, SharedSupplyPolicy::class);
        SharedSupply::observe(SharedSupplyObserver::class);

        Gate::define('accessCoTeamFeatures', fn (?User $user): bool => TeamMenuContext::canAccessCoOnlyFeatures($user));

        /** CS 기관 이슈 — CS 팀·관리자(및 팀 미지정 레거시 계정) */
        Gate::define('accessCsTeamFeatures', fn (?User $user): bool => $user !== null
            && ($user->hasPlatformWideViewAccess() || in_array(TeamMenuContext::resolveTeamCode($user), ['CS', ''], true)));

        /** 잠재기관 리스트/보기 — CO 팀·관리자(및 팀 미지정 레거시 계정) */
        Gate::define('managePotentialInstitutions', fn (?User $user): bool => TeamMenuContext::canAccessCoOnlyFeatures($user));

        Gate::define('deleteTeamStructure', fn (?User $user): bool => (bool) ($user?->canDeletePlatformData()));

        Gate::define('deleteContactRecords', fn (?User $user): bool => (bool) ($user?->canDeletePlatformData()));

        Gate::define('createContactRecord', function (?User $user, ?string $skCode = null): bool {
            if ($user === null) {
                return false;
            }

            if ($user->hasFullAccess()) {
                return true;
            }

            $institutionQuery = app(InstitutionAccountListQuery::class);

            $trimmedSkCode = trim((string) $skCode);
            if ($trimmedSkCode === '') {
                return $institutionQuery->hasAnyManageableInstitutionForCurrentUser();
            }

            return $institutionQuery->currentUserCanManageInstitution($trimmedSkCode);
        });

        Gate::define('updateContactRecord', function (?User $user, Teacher $teacher): bool {
            if ($user === null) {
                return false;
            }

            if ($user->hasFullAccess()) {
                return true;
            }

            return app(InstitutionAccountListQuery::class)
                ->currentUserCanManageInstitution((string) ($teacher->SK_Code ?? ''));
        });

        /** 기관 지원 보고서(S_SupportInfo_Account) 삭제 — 관리자만 */
        Gate::define('deleteSupportRecords', fn (?User $user): bool => (bool) ($user?->canDeletePlatformData()));

        /** 기관 지원 보고서 수정 — 관리자 전체, 일반 사용자는 본인 담당(TR_Name) 건만 */
        Gate::define('updateSupportRecord', function (?User $user, SupportRecord $record): bool {
            if ($user === null) {
                return false;
            }

            if ($user->hasFullAccess()) {
                return true;
            }

            $authorKey = ManagerNameNormalizer::normalize((string) ($record->TR_Name ?? ''));
            $userKey = ManagerNameNormalizer::normalize($user->nameForCoReports());

            return $authorKey !== '' && $userKey !== '' && $authorKey === $userKey;
        });

        /** MOCHI 교사 지원 보고서 수정 — 관리자 전체, 일반 사용자는 작성자(created_by/coach_name) + 담당 범위 */
        Gate::define('updateTeacherSupportReport', function (?User $user, string $table, int $reportId): bool {
            if ($user === null) {
                return false;
            }

            if (TeacherSupportReportEditAuthorization::isLegacyTable($table)) {
                $row = TeacherSupportReportEditAuthorization::findLegacyReport($table, $reportId);
                if ($row === null) {
                    return false;
                }

                $teacherId = TeacherSupportReportEditAuthorization::legacyTeacherIdFromRow($table, $row);
                if ($teacherId === null) {
                    return false;
                }

                $teacher = Teacher::query()->find($teacherId);
                if ($teacher === null) {
                    return false;
                }

                return TeacherSupportReportEditAuthorization::canUpdateLegacy($user, $row, $teacher);
            }

            $report = TeacherSupportReportEditAuthorization::findMochiReport($table, $reportId);
            if ($report === null) {
                return false;
            }

            $teacher = Teacher::query()->find((int) $report->getAttribute('teacher_id'));
            if ($teacher === null) {
                return false;
            }

            return TeacherSupportReportEditAuthorization::canUpdate($user, $report, $teacher);
        });

        /** 잠재기관 미팅/컨설팅 이력 삭제 — 관리자만 */
        Gate::define('deletePotentialMeetingDetails', fn (?User $user): bool => (bool) ($user?->canDeletePlatformData()));

        /** 잠재기관(CoNewTarget) 삭제 — 관리자만 (미계약만 허용은 컴포넌트에서 추가 검증) */
        Gate::define('deletePotentialInstitutions', fn (?User $user): bool => (bool) ($user?->canDeletePlatformData()));
    }
}
