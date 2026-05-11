<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sk_code_requests')) {
            return;
        }

        Schema::table('sk_code_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('sk_code_requests', 'co')) {
                $table->string('co', 255)->nullable()->after('account_no');
            }

            if (! Schema::hasColumn('sk_code_requests', 'tr')) {
                $table->string('tr', 255)->nullable()->after('co');
            }

            if (! Schema::hasColumn('sk_code_requests', 'cs')) {
                $table->string('cs', 255)->nullable()->after('tr');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sk_code_requests')) {
            return;
        }

        Schema::table('sk_code_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('sk_code_requests', 'cs')) {
                $table->dropColumn('cs');
            }

            if (Schema::hasColumn('sk_code_requests', 'tr')) {
                $table->dropColumn('tr');
            }

            if (Schema::hasColumn('sk_code_requests', 'co')) {
                $table->dropColumn('co');
            }
        });
    }
};
