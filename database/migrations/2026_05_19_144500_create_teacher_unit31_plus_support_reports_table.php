<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_unit31_plus_support_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->unsignedTinyInteger('progress_unit')->nullable();
            $table->unsignedTinyInteger('progress_lesson')->nullable();
            $table->string('progress_other', 255)->nullable();
            $table->json('procedures')->nullable();
            $table->json('verbal_materials')->nullable();
            $table->json('language_arts_materials')->nullable();
            $table->text('verbal_comments')->nullable();
            $table->text('language_arts_comments')->nullable();
            $table->text('overall_comments')->nullable();
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
        Schema::dropIfExists('teacher_unit31_plus_support_reports');
    }
};
