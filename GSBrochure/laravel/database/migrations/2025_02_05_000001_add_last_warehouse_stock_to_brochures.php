<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brochures', function (Blueprint $table) {
            $table->integer('last_warehouse_stock_quantity')->default(0)->after('last_stock_date');
            $table->string('last_warehouse_stock_date')->nullable()->after('last_warehouse_stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('brochures', function (Blueprint $table) {
            $table->dropColumn(['last_warehouse_stock_quantity', 'last_warehouse_stock_date']);
        });
    }
};
