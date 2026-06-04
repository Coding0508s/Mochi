<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shared_supply_labels')) {
            Schema::create('shared_supply_labels', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        DB::table('shared_supply_labels')->upsert([
            ['code' => '01', 'name' => '차량배차', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '02', 'name' => '회의실', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ], ['code'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_supply_labels');
    }
};
