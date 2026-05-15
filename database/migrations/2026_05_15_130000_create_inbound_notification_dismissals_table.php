<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inbound_notification_dismissals')) {
            return;
        }

        Schema::create('inbound_notification_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('log_id')->constrained('external_assignment_inbound_logs')->cascadeOnDelete();
            $table->timestamp('dismissed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'log_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_notification_dismissals');
    }
};
