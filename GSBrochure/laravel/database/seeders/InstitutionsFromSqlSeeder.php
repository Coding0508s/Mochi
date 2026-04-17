<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionsFromSqlSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('institutions 2.sql');
        if (! is_file($path)) {
            $this->command->warn('institutions 2.sql not found. Skipping.');

            return;
        }

        $sql = file_get_contents($path);

        // Extract only the INSERT INTO ... VALUES ... ; part (skip CREATE, SET, COMMIT, etc.)
        if (! preg_match('/INSERT\s+INTO\s+`?institutions`?\s+\([^)]+\)\s+VALUES\s+(.+?);/s', $sql, $m)) {
            $this->command->warn('Could not find INSERT statement in SQL file.');

            return;
        }

        $insertBody = trim($m[1]);
        // Remove trailing comma if present (e.g. "..., (294,...);" then we have "..., (294,...)" after VALUES
        $insertBody = rtrim($insertBody, " \t\n\r,");

        DB::table('institutions')->truncate();

        $fullInsert = 'INSERT INTO institutions (id, name, type, description, is_active, sort_order, created_at, updated_at) VALUES ' . $insertBody;
        DB::unprepared($fullInsert);

        $count = DB::table('institutions')->count();
        $this->command->info("Inserted {$count} institutions.");
    }
}
