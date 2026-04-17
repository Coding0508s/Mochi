<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_history', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // '입고' or '출고'
            $table->string('date');
            $table->foreignId('brochure_id')->constrained('brochures');
            $table->string('brochure_name');
            $table->integer('quantity');
            $table->string('contact_name')->nullable();
            $table->string('schoolname')->nullable();
            $table->integer('before_stock');
            $table->integer('after_stock');
            $table->timestamps();
        });
        Schema::table('stock_history', function (Blueprint $table) {
            $table->index('date');
            $table->index('brochure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_history');
    }
};
