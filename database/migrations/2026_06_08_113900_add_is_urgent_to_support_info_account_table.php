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

        if (Schema::hasColumn('S_SupportInfo_Account', 'is_urgent')) {
            return;
        }

        Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->boolean('is_urgent')->default(false)->after('CompletedDate');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return;
        }

        if (! Schema::hasColumn('S_SupportInfo_Account', 'is_urgent')) {
            return;
        }

        Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->dropColumn('is_urgent');
        });
    }
};
