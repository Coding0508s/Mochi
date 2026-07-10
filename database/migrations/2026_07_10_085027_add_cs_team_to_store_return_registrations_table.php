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
        Schema::table('store_return_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('store_return_registrations', 'cs_team')) {
                $table->string('cs_team', 100)->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table) {
            $table->dropColumn('cs_team');
        });
    }
};
