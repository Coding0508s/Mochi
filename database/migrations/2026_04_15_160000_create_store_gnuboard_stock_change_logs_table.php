<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_gnuboard_stock_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code', 40)->index();
            $table->unsignedInteger('before_qty')->default(0);
            $table->unsignedInteger('after_qty')->default(0);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 40)->default('store_inventory')->index();
            $table->string('memo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_gnuboard_stock_change_logs');
    }
};
