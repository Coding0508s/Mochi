<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('setup_roles')) {
            return;
        }

        Schema::table('setup_roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('setup_roles', 'account_flags')) {
                $table->json('account_flags')->nullable()->after('permissions');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('setup_roles')) {
            return;
        }

        Schema::table('setup_roles', function (Blueprint $table): void {
            if (Schema::hasColumn('setup_roles', 'account_flags')) {
                $table->dropColumn('account_flags');
            }
        });
    }
};
