<?php

namespace Database\Factories;

use App\Models\JobTitlePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTitlePermission>
 */
class JobTitlePermissionFactory extends Factory
{
    protected $model = JobTitlePermission::class;

    public function definition(): array
    {
        return [
            'job_code' => fake()->unique()->jobTitle(),
            'setup_view' => false,
            'setup_manage' => false,
            'can_manage_store_inventory' => false,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => false,
            'can_view_all_institutions' => false,
            'is_deputy_admin' => false,
        ];
    }
}
