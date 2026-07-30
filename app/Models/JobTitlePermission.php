<?php

namespace App\Models;

use Database\Factories\JobTitlePermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobTitlePermission extends Model
{
    /** @use HasFactory<JobTitlePermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'job_code',
        'setup_view',
        'setup_manage',
        'can_manage_store_inventory',
        'is_gs_brochure_admin',
        'is_coach_team_lead',
        'can_view_all_institutions',
        'is_deputy_admin',
    ];

    protected function casts(): array
    {
        return [
            'setup_view' => 'boolean',
            'setup_manage' => 'boolean',
            'can_manage_store_inventory' => 'boolean',
            'is_gs_brochure_admin' => 'boolean',
            'is_coach_team_lead' => 'boolean',
            'can_view_all_institutions' => 'boolean',
            'is_deputy_admin' => 'boolean',
        ];
    }
}
