<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institution_visibility_overrides')) {
            return;
        }

        Schema::create('institution_visibility_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('sk_code', 100)->unique();
            $table->string('hidden_reason', 100)->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_visibility_overrides');
    }
};
