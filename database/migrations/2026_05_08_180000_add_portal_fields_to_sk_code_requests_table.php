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
            if (! Schema::hasColumn('sk_code_requests', 'portal_campus_id')) {
                $table->string('portal_campus_id', 100)->nullable()->after('final_sk_code');
            }

            if (! Schema::hasColumn('sk_code_requests', 'account_no')) {
                $table->string('account_no', 100)->nullable()->after('portal_campus_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sk_code_requests')) {
            return;
        }

        Schema::table('sk_code_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('sk_code_requests', 'account_no')) {
                $table->dropColumn('account_no');
            }

            if (Schema::hasColumn('sk_code_requests', 'portal_campus_id')) {
                $table->dropColumn('portal_campus_id');
            }
        });
    }
};
