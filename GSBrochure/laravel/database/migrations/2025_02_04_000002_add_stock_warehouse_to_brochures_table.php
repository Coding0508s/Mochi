<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brochures', function (Blueprint $table) {
            $table->integer('stock_warehouse')->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('brochures', function (Blueprint $table) {
            $table->dropColumn('stock_warehouse');
        });
    }
};
