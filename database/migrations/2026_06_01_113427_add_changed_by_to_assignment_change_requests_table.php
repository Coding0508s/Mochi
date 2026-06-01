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
        Schema::table('assignment_change_requests', function (Blueprint $table) {
            $table->string('changed_by', 255)->nullable()->after('cs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_change_requests', function (Blueprint $table) {
            $table->dropColumn('changed_by');
        });
    }
};
