<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('S_AccountName')) {
            return;
        }

        if (! Schema::hasColumn('S_AccountName', 'Possibility')) {
            Schema::table('S_AccountName', function (Blueprint $table): void {
                $table->string('Possibility', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('S_AccountName')) {
            return;
        }

        if (Schema::hasColumn('S_AccountName', 'Possibility')) {
            Schema::table('S_AccountName', function (Blueprint $table): void {
                $table->dropColumn('Possibility');
            });
        }
    }
};
