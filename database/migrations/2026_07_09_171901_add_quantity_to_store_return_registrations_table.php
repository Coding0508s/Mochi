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
            if (! Schema::hasColumn('store_return_registrations', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('item_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('store_return_registrations', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};
