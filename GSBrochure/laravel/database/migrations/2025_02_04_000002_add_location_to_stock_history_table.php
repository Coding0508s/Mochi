<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_history', function (Blueprint $table) {
            $table->string('location', 20)->nullable()->after('type')->comment('warehouse=물류센터, hq=본사');
        });
    }

    public function down(): void
    {
        Schema::table('stock_history', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
