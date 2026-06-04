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
            ['code' => '00018', 'name' => '오전 반차', 'is_active' => true, 'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00019', 'name' => '오후 반차', 'is_active' => true, 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00020', 'name' => '시차', 'is_active' => true, 'sort_order' => 14, 'created_at' => now(), 'updated_at' => now()],
        ], ['code'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('shared_supply_items')) {
            return;
        }

        DB::table('shared_supply_items')
            ->whereIn('code', ['00018', '00019', '00020'])
            ->delete();
    }
};
