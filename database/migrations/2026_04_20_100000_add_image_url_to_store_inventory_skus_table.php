<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_inventory_skus', function (Blueprint $table): void {
            if (! Schema::hasColumn('store_inventory_skus', 'image_url')) {
                $table->string('image_url', 2048)->nullable()->after('memo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_inventory_skus', function (Blueprint $table): void {
            if (Schema::hasColumn('store_inventory_skus', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};
