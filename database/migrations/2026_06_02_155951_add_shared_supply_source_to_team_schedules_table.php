<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('team_schedules', 'source_type')) {
                $table->string('source_type', 50)->nullable()->after('location');
            }
            if (! Schema::hasColumn('team_schedules', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });

        try {
            Schema::table('team_schedules', function (Blueprint $table): void {
                $table->index(['source_type', 'source_id'], 'team_schedules_source_idx');
            });
        } catch (Throwable) {
            // ignore when index already exists
        }

        try {
            Schema::table('team_schedules', function (Blueprint $table): void {
                $table->unique(['source_type', 'source_id'], 'team_schedules_source_unique');
            });
        } catch (Throwable) {
            // ignore when unique index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_schedules', function (Blueprint $table): void {
            try {
                $table->dropUnique('team_schedules_source_unique');
            } catch (Throwable) {
                // ignore when index does not exist
            }

            try {
                $table->dropIndex('team_schedules_source_idx');
            } catch (Throwable) {
                // ignore when index does not exist
            }

            if (Schema::hasColumn('team_schedules', 'source_id')) {
                $table->dropColumn('source_id');
            }
            if (Schema::hasColumn('team_schedules', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
