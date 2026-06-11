<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('account_audit_logs')) {
            return;
        }

        Schema::table('account_audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('account_audit_logs', 'user_id')) {
                $table->foreignId('user_id')->after('id')->constrained('users')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('account_audit_logs', 'actor_id')) {
                $table->foreignId('actor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('account_audit_logs', 'action')) {
                $table->string('action', 80)->after('actor_id');
            }

            if (! Schema::hasColumn('account_audit_logs', 'changes')) {
                $table->json('changes')->nullable()->after('action');
            }
        });

        if (Schema::hasColumn('account_audit_logs', 'updated_at')) {
            Schema::table('account_audit_logs', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }

        if (! Schema::hasColumn('account_audit_logs', 'created_at')) {
            Schema::table('account_audit_logs', function (Blueprint $table): void {
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('account_audit_logs')) {
            return;
        }

        Schema::table('account_audit_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('account_audit_logs', 'changes')) {
                $table->dropColumn('changes');
            }

            if (Schema::hasColumn('account_audit_logs', 'action')) {
                $table->dropColumn('action');
            }

            if (Schema::hasColumn('account_audit_logs', 'actor_id')) {
                $table->dropForeign(['actor_id']);
                $table->dropColumn('actor_id');
            }

            if (Schema::hasColumn('account_audit_logs', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
