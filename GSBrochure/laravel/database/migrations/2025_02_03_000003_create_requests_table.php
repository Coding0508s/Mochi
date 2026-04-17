<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('date');
            $table->string('schoolname');
            $table->string('address');
            $table->string('phone');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->timestamps();
        });
        Schema::table('requests', function (Blueprint $table) {
            $table->index('date');
            $table->index('schoolname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
