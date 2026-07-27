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
        if (! Schema::hasTable('Teachers')) {
            return;
        }

        if (Schema::hasColumn('Teachers', 'EmploymentType')) {
            return;
        }

        Schema::table('Teachers', function (Blueprint $table) {
            $table->string('EmploymentType', 32)
                ->default('unspecified')
                ->after('Status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('Teachers')) {
            return;
        }

        if (! Schema::hasColumn('Teachers', 'EmploymentType')) {
            return;
        }

        Schema::table('Teachers', function (Blueprint $table) {
            $table->dropColumn('EmploymentType');
        });
    }
};
