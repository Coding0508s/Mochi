<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'setup_role_id')) {
                $table->foreignId('setup_role_id')
                    ->nullable()
                    ->after('is_deputy_admin')
                    ->constrained('setup_roles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'setup_role_id')) {
                $table->dropForeign(['setup_role_id']);
                $table->dropColumn('setup_role_id');
            }
        });
    }
};
