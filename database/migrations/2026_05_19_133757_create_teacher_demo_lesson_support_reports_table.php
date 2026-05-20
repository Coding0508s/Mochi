<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_demo_lesson_support_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->unsignedTinyInteger('progress_unit')->nullable();
            $table->unsignedTinyInteger('progress_lesson')->nullable();
            $table->text('other_notes')->nullable();
            $table->json('procedures')->nullable();
            $table->json('verbal_tools')->nullable();
            $table->json('language_arts_tools')->nullable();
            $table->text('comments_primary')->nullable();
            $table->text('comments_secondary')->nullable();
            $table->json('evaluations')->nullable();
            $table->text('overall_comments')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('teacher_id');
            $table->index('sk_code');
            $table->index('support_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_demo_lesson_support_reports');
    }
};
