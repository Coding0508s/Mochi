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

        if (! Schema::hasColumn('S_SupportInfo_Account', 'Issue')) {
            return;
        }

        Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->text('Issue')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('S_SupportInfo_Account')) {
            return;
        }

        if (! Schema::hasColumn('S_SupportInfo_Account', 'Issue')) {
            return;
        }

        Schema::table('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->string('Issue', 255)->nullable()->change();
        });
    }
};
