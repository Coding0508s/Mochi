<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teacher_visit_support_reports')) {
            return;
        }

        if (Schema::hasColumn('teacher_visit_support_reports', 'observe_curriculum_rows')) {
            return;
        }

        Schema::table('teacher_visit_support_reports', function (Blueprint $table): void {
            $table->json('observe_curriculum_rows')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teacher_visit_support_reports')) {
            return;
        }

        if (! Schema::hasColumn('teacher_visit_support_reports', 'observe_curriculum_rows')) {
            return;
        }

        Schema::table('teacher_visit_support_reports', function (Blueprint $table): void {
            $table->dropColumn('observe_curriculum_rows');
        });
    }
};
