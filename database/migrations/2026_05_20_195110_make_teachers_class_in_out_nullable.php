<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('Teachers') || ! Schema::hasColumn('Teachers', 'ClassInOut')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE Teachers ALTER COLUMN ClassInOut BIT NULL');

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE Teachers MODIFY ClassInOut TINYINT(1) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('Teachers') || ! Schema::hasColumn('Teachers', 'ClassInOut')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE Teachers ALTER COLUMN ClassInOut BIT NOT NULL');

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE Teachers MODIFY ClassInOut TINYINT(1) NOT NULL');
        }
    }
};
