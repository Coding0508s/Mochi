<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('invoice_number');
            $table->timestamps();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
