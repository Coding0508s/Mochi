<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_return_ecount_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('store_return_ecount_products', 'product_name')) {
                $table->string('product_name', 255)->nullable()->after('prod_cd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_return_ecount_products', function (Blueprint $table): void {
            if (Schema::hasColumn('store_return_ecount_products', 'product_name')) {
                $table->dropColumn('product_name');
            }
        });
    }
};
