<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_usage_logs')) {
            return;
        }

        Schema::table('vehicle_usage_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_usage_logs', 'institution_sk_code')) {
                $table->string('institution_sk_code')->nullable()->after('usage_purpose_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vehicle_usage_logs')) {
            return;
        }

        Schema::table('vehicle_usage_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('vehicle_usage_logs', 'institution_sk_code')) {
                $table->dropColumn('institution_sk_code');
            }
        });
    }
};
