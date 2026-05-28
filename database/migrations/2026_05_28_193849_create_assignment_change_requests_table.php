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
        Schema::create('assignment_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('sk_code', 100)->index();
            $table->string('co')->nullable();
            $table->string('tr')->nullable();
            $table->string('cs')->nullable();
            $table->enum('origin', ['A', 'K']);
            $table->enum('status', ['pending', 'applied', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['origin', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_change_requests');
    }
};
