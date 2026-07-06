<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('Teachers')) {
            return;
        }

        if (! Schema::hasColumn('Teachers', 'Description')) {
            return;
        }

        Schema::table('Teachers', function (Blueprint $table): void {
            $table->text('Description')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('Teachers')) {
            return;
        }

        if (! Schema::hasColumn('Teachers', 'Description')) {
            return;
        }

        Schema::table('Teachers', function (Blueprint $table): void {
            $table->string('Description', 255)->nullable()->change();
        });
    }
};
