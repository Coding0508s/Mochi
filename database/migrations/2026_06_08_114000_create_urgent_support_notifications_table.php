<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urgent_support_notifications')) {
            return;
        }

        Schema::create('urgent_support_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('support_record_id');
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('sk_code', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'is_read']);
            $table->index('support_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urgent_support_notifications');
    }
};
