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
            ['code' => '00021', 'name' => '[출장] 출장', 'is_active' => true, 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00022', 'name' => '[본부회의] 본부회의', 'is_active' => true, 'sort_order' => 16, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00023', 'name' => '[전체회의] 전체회의', 'is_active' => true, 'sort_order' => 17, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00024', 'name' => '[해외출장] 해외출장', 'is_active' => true, 'sort_order' => 18, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00025', 'name' => '[사내외업무] 사내외업무', 'is_active' => true, 'sort_order' => 19, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00026', 'name' => '[사내외행사] 사내외행사', 'is_active' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00027', 'name' => '[경조사] 경조사', 'is_active' => true, 'sort_order' => 21, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00028', 'name' => '[건강검진] 건강검진', 'is_active' => true, 'sort_order' => 22, 'created_at' => now(), 'updated_at' => now()],
        ], ['code'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('shared_supply_items')) {
            return;
        }

        DB::table('shared_supply_items')
            ->whereIn('code', ['00021', '00022', '00023', '00024', '00025', '00026', '00027', '00028'])
            ->delete();
    }
};
