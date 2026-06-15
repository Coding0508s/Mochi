<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shared_supply_items')) {
            return;
        }

        DB::table('shared_supply_items')->upsert([
            ['code' => '00029', 'name' => '종일', 'is_active' => true, 'sort_order' => 23, 'created_at' => now(), 'updated_at' => now()],
        ], ['code'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('shared_supply_items')) {
            return;
        }

        DB::table('shared_supply_items')
            ->where('code', '00029')
            ->delete();
    }
};
