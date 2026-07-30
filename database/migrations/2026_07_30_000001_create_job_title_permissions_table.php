<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_title_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('job_code')->unique();
            $table->boolean('setup_view')->default(false);
            $table->boolean('setup_manage')->default(false);
            $table->boolean('can_manage_store_inventory')->default(false);
            $table->boolean('is_gs_brochure_admin')->default(false);
            $table->boolean('is_coach_team_lead')->default(false);
            $table->boolean('can_view_all_institutions')->default(false);
            $table->boolean('is_deputy_admin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_title_permissions');
    }
};
