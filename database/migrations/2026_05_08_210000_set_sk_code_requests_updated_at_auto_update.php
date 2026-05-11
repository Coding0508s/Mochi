<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sk_code_requests') || ! Schema::hasColumn('sk_code_requests', 'updated_at')) {
            return;
        }

        if (! $this->supportsOnUpdateTimestamp()) {
            return;
        }

        DB::statement(
            'ALTER TABLE sk_code_requests MODIFY updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('sk_code_requests') || ! Schema::hasColumn('sk_code_requests', 'updated_at')) {
            return;
        }

        if (! $this->supportsOnUpdateTimestamp()) {
            return;
        }

        DB::statement(
            'ALTER TABLE sk_code_requests MODIFY updated_at TIMESTAMP NULL DEFAULT NULL'
        );
    }

    private function supportsOnUpdateTimestamp(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
