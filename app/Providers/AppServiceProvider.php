<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('editEmployeeProfile', fn (?User $user): bool => $user !== null);

        Gate::define('manageEmployeeDepartment', fn (?User $user): bool => (bool) ($user?->is_admin));

        Gate::define('manageTeamStructure', fn (?User $user): bool => (bool) ($user?->is_admin));
    }
}
