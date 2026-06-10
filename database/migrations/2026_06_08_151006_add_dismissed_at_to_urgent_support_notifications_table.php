<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urgent_support_notifications')) {
            return;
        }

        if (Schema::hasColumn('urgent_support_notifications', 'dismissed_at')) {
            return;
        }

        Schema::table('urgent_support_notifications', function (Blueprint $table): void {
            $table->timestamp('dismissed_at')->nullable()->after('read_at');
            $table->index(['recipient_user_id', 'dismissed_at'], 'usrg_rcpt_dismiss_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('urgent_support_notifications')) {
            return;
        }

        if (! Schema::hasColumn('urgent_support_notifications', 'dismissed_at')) {
            return;
        }

        Schema::table('urgent_support_notifications', function (Blueprint $table): void {
            $table->dropIndex('usrg_rcpt_dismiss_idx');
            $table->dropColumn('dismissed_at');
        });
    }
};
