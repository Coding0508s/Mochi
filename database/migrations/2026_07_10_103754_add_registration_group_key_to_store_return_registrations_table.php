<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('store_return_registrations', 'registration_group_key')) {
                $table->string('registration_group_key', 36)->nullable()->index()->after('registered_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table): void {
            if (Schema::hasColumn('store_return_registrations', 'registration_group_key')) {
                $table->dropColumn('registration_group_key');
            }
        });
    }
};
