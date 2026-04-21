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

        if (! Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
            Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
                $table->unsignedInteger('potential_target_id')->nullable()->after('SK_Code');
                $table->index('potential_target_id', 's_supportinfo_account_potential_target_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return;
        }

        if (Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
            Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
                $table->dropIndex('s_supportinfo_account_potential_target_id_idx');
                $table->dropColumn('potential_target_id');
            });
        }
    }
};
