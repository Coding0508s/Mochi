<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'last_inbound_seen_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_inbound_seen_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'last_inbound_seen_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_inbound_seen_at');
        });
    }
};
