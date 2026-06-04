<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shared_supplies') || ! Schema::hasTable('shared_supply_labels')) {
            return;
        }

        Schema::table('shared_supplies', function (Blueprint $table): void {
            if (! Schema::hasColumn('shared_supplies', 'shared_supply_label_id')) {
                $table->unsignedBigInteger('shared_supply_label_id')->nullable()->after('shared_supply_item_id');
                $table->index(['shared_supply_label_id']);
            }
        });

        $defaultLabelId = (int) DB::table('shared_supply_labels')->where('code', '01')->value('id');
        if ($defaultLabelId > 0) {
            DB::table('shared_supplies')
                ->whereNull('shared_supply_label_id')
                ->update(['shared_supply_label_id' => $defaultLabelId]);
        }

        try {
            Schema::table('shared_supplies', function (Blueprint $table): void {
                $table->foreign('shared_supply_label_id')
                    ->references('id')
                    ->on('shared_supply_labels')
                    ->nullOnDelete();
            });
        } catch (Throwable) {
            // 외래키가 이미 있거나 DB 상태상 생성 불가하면 무시
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shared_supplies')) {
            return;
        }

        try {
            Schema::table('shared_supplies', function (Blueprint $table): void {
                $table->dropForeign(['shared_supply_label_id']);
            });
        } catch (Throwable) {
            // 외래키가 없으면 무시
        }

        Schema::table('shared_supplies', function (Blueprint $table): void {
            if (Schema::hasColumn('shared_supplies', 'shared_supply_label_id')) {
                $table->dropColumn('shared_supply_label_id');
            }
        });
    }
};
