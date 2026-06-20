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
        Schema::table('teacher_visit_support_reports', function (Blueprint $table) {
            $table->index(
                ['teacher_id', 'support_date', 'status'],
                'visit_teacher_support_date_status_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_visit_support_reports', function (Blueprint $table) {
            $table->dropIndex('visit_teacher_support_date_status_idx');
        });
    }
};
