<?php

namespace App\Providers;

use App\Models\User;
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

        Gate::define('manageTeamStructure', fn (?User $user): bool => (bool) ($user?->hasFullAccess()));

        Gate::define('manageStoreInventory', fn (?User $user): bool => (bool) ($user?->hasFullAccess() || $user?->can_manage_store_inventory));

        Gate::define('manageGsBrochureAdmin', fn (?User $user): bool => (bool) ($user?->hasFullAccess() || $user?->is_gs_brochure_admin));

        Gate::define('manageUserAccounts', fn (?User $user): bool => (bool) ($user?->hasFullAccess()));

        /** 잠재기관 리스트/보기에서 미팅 추가 등 (로그인 사용자 — 라우트가 auth 그룹) */
        Gate::define('managePotentialInstitutions', fn (?User $user): bool => $user !== null);
    }
}
