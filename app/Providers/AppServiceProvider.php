<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // GS Brochure 레거시 Blade를 통합 앱에서 직접 렌더링하기 위한 뷰 경로 등록
        View::addLocation(base_path('GSBrochure/laravel/resources/views'));

        Gate::define('editEmployeeProfile', fn (?User $user): bool => $user !== null);

        Gate::define('manageEmployeeDepartment', fn (?User $user): bool => (bool) ($user?->is_admin));

        Gate::define('manageTeamStructure', fn (?User $user): bool => (bool) ($user?->is_admin));

        Gate::define('manageStoreInventory', fn (?User $user): bool => (bool) ($user?->is_admin));

        Gate::define('manageGsBrochureAdmin', fn (?User $user): bool => (bool) ($user?->is_admin));
    }
}
