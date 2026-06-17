<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'setup_view')) {
                $table->boolean('setup_view')->default(false)->after('is_deputy_admin');
            }

            if (! Schema::hasColumn('users', 'setup_manage')) {
                $table->boolean('setup_manage')->default(false)->after('setup_view');
            }
        });

        DB::table('users')
            ->where('is_admin', true)
            ->update([
                'setup_view' => true,
                'setup_manage' => true,
            ]);

        DB::table('users')
            ->where('is_admin', false)
            ->where('is_deputy_admin', true)
            ->update([
                'setup_view' => true,
                'setup_manage' => false,
            ]);

        if (! Schema::hasTable('setup_roles') || ! Schema::hasColumn('users', 'setup_role_id')) {
            return;
        }

        $assignments = DB::table('users')
            ->join('setup_roles', 'setup_roles.id', '=', 'users.setup_role_id')
            ->select(['users.id', 'setup_roles.permissions'])
            ->get();

        foreach ($assignments as $assignment) {
            $permissions = json_decode((string) ($assignment->permissions ?? '[]'), true);
            $setupView = (bool) ($permissions['setup']['view'] ?? false);
            $setupManage = (bool) ($permissions['setup']['update'] ?? false);

            DB::table('users')
                ->where('id', $assignment->id)
                ->update([
                    'setup_view' => $setupView || $setupManage,
                    'setup_manage' => $setupManage,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'setup_manage')) {
                $table->dropColumn('setup_manage');
            }

            if (Schema::hasColumn('users', 'setup_view')) {
                $table->dropColumn('setup_view');
            }
        });
    }
};
