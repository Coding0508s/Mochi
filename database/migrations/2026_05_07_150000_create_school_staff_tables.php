<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ConsultantID 매핑 (ID 1~21)
        if (! Schema::hasTable('school_consultants')) {
            Schema::create('school_consultants', function (Blueprint $table): void {
                $table->unsignedInteger('consultant_id')->primary();
                $table->string('name', 100);
            });
        }

        // CoachID 매핑 (ID 1000~30000)
        if (! Schema::hasTable('school_coaches')) {
            Schema::create('school_coaches', function (Blueprint $table): void {
                $table->unsignedInteger('coach_id')->primary();
                $table->string('name', 100);
            });
        }

        // CsID 매핑 (ID 1000~13000)
        if (! Schema::hasTable('school_cs_staff')) {
            Schema::create('school_cs_staff', function (Blueprint $table): void {
                $table->unsignedInteger('cs_id')->primary();
                $table->string('name', 100);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_cs_staff');
        Schema::dropIfExists('school_coaches');
        Schema::dropIfExists('school_consultants');
    }
};
