<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('teacher_visit_support_reports', 'support_location')) {
            return;
        }

        Schema::table('teacher_visit_support_reports', function (Blueprint $table): void {
            $table->string('support_location', 255)->nullable()->after('support_date');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_visit_support_reports', function (Blueprint $table): void {
            $table->dropColumn('support_location');
        });
    }
};
