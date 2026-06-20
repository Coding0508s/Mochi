<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_visit_support_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('support_location', 255)->nullable();
            $table->string('support_purpose', 100);
            $table->unsignedTinyInteger('observe_unit')->nullable();
            $table->unsignedTinyInteger('observe_lesson')->nullable();
            $table->string('observe_summary_extra', 255)->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('meeting_type', 50)->nullable();
            $table->text('pre_request_notes')->nullable();
            $table->text('monitoring_feedback')->nullable();
            $table->text('interview_and_action_plan')->nullable();
            $table->text('special_notes')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('teacher_id');
            $table->index('sk_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_visit_support_reports');
    }
};
