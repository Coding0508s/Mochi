<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return;
        }

        if (Schema::hasColumn('S_SupportInfo_Account', 'record_kind')) {
            return;
        }

        Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
            // null = 기존 기관 지원 보고서, 'issue' = CS 기관 이슈
            $table->string('record_kind', 20)->nullable()->after('is_urgent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return;
        }

        if (! Schema::hasColumn('S_SupportInfo_Account', 'record_kind')) {
            return;
        }

        Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->dropColumn('record_kind');
        });
    }
};
