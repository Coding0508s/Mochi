<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'is_deputy_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $column = $table->boolean('is_deputy_admin')->default(false);

            if (Schema::hasColumn('users', 'is_coach_team_lead')) {
                $column->after('is_coach_team_lead');
            } else {
                $column->after('can_manage_store_inventory');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'is_deputy_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_deputy_admin');
        });
    }
};
