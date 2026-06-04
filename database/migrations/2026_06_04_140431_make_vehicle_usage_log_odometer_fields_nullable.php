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
        if (! Schema::hasTable('vehicle_usage_logs')) {
            return;
        }

        Schema::table('vehicle_usage_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('vehicle_usage_logs', 'odometer_before')) {
                $table->unsignedInteger('odometer_before')->nullable()->change();
            }

            if (Schema::hasColumn('vehicle_usage_logs', 'odometer_after')) {
                $table->unsignedInteger('odometer_after')->nullable()->change();
            }

            if (Schema::hasColumn('vehicle_usage_logs', 'distance')) {
                $table->unsignedInteger('distance')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('vehicle_usage_logs')) {
            return;
        }

        Schema::table('vehicle_usage_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('vehicle_usage_logs', 'odometer_before')) {
                $table->unsignedInteger('odometer_before')->nullable(false)->change();
            }

            if (Schema::hasColumn('vehicle_usage_logs', 'odometer_after')) {
                $table->unsignedInteger('odometer_after')->nullable(false)->change();
            }

            if (Schema::hasColumn('vehicle_usage_logs', 'distance')) {
                $table->unsignedInteger('distance')->nullable(false)->change();
            }
        });
    }
};
