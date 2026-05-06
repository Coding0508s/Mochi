<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_assignment_inbound_logs')) {
            return;
        }

        Schema::create('external_assignment_inbound_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('sk_code', 100);
            $table->string('co')->nullable();
            $table->string('tr')->nullable();
            $table->string('cs')->nullable();
            $table->json('raw_body')->nullable();
            $table->string('status', 20)->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['sk_code', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_assignment_inbound_logs');
    }
};
