<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sk_code_requests')) {
            return;
        }

        Schema::create('sk_code_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('co_new_target_id');
            $table->string('institution_name', 200);
            $table->string('temp_sk_code', 64);
            $table->string('final_sk_code', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'applied_at']);
            $table->index('co_new_target_id');
            $table->index('temp_sk_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_code_requests');
    }
};
