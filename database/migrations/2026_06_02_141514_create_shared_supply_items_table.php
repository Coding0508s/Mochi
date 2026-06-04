<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shared_supply_items')) {
            Schema::create('shared_supply_items', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 255);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        DB::table('shared_supply_items')->upsert([
            ['code' => '00003', 'name' => '04부8326 (투싼/경유)-구미김천역', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00005', 'name' => '29구9162 (투싼/경유)', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00008', 'name' => '62노5836 (아반테/경유)', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00010', 'name' => '169허7622(뉴그랜저)', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00011', 'name' => '236주3346 (투싼/가솔린)', 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00012', 'name' => '100누7588 (투싼/가솔린)-부산역', 'is_active' => true, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00013', 'name' => 'Grape Room', 'is_active' => true, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00014', 'name' => 'Jenny Room', 'is_active' => true, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00015', 'name' => 'Jonny Room', 'is_active' => true, 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00016', 'name' => '133누7691(투싼/가솔린)', 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '00017', 'name' => 'GrapeSEED Services', 'is_active' => true, 'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
        ], ['code'], ['name', 'is_active', 'sort_order', 'updated_at']);

        if (Schema::hasTable('shared_supplies')) {
            Schema::table('shared_supplies', function (Blueprint $table): void {
                if (! Schema::hasColumn('shared_supplies', 'shared_supply_item_id')) {
                    // 기존 DB(초기 버전) 호환: 컬럼이 없으면 우선 nullable로 추가
                    $table->unsignedBigInteger('shared_supply_item_id')->nullable()->after('ends_at');
                    $table->index(['shared_supply_item_id', 'starts_at']);
                }
            });

            try {
                Schema::table('shared_supplies', function (Blueprint $table): void {
                    $table->foreign('shared_supply_item_id')
                        ->references('id')
                        ->on('shared_supply_items')
                        ->cascadeOnDelete();
                });
            } catch (Throwable) {
                // 외래키가 이미 있거나, DB 상태에 따라 재생성 불가 시 무시
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shared_supplies')) {
            try {
                Schema::table('shared_supplies', function (Blueprint $table): void {
                    $table->dropForeign(['shared_supply_item_id']);
                });
            } catch (Throwable) {
                // 외래키가 없으면 무시
            }
        }

        Schema::dropIfExists('shared_supply_items');
    }
};
