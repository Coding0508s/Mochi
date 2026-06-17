<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'setup_role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['setup_role_id']);
                $table->dropColumn('setup_role_id');
            });
        }

        Schema::dropIfExists('setup_roles');
    }

    public function down(): void
    {
        if (! Schema::hasTable('setup_roles')) {
            Schema::create('setup_roles', function (Blueprint $table): void {
                $table->id();
                $table->string('role_key', 40)->unique();
                $table->string('role_name', 80);
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('permissions')->nullable();
                $table->json('account_flags')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'setup_role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('setup_role_id')
                    ->nullable()
                    ->after('is_deputy_admin')
                    ->constrained('setup_roles')
                    ->nullOnDelete();
            });
        }
    }
};
