<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('brochure_id')->constrained('brochures');
            $table->string('brochure_name');
            $table->integer('quantity');
            $table->timestamps();
        });
        Schema::table('request_items', function (Blueprint $table) {
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
