<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('shared_supplies')) {
            return;
        }

        Schema::table('shared_supplies', function (Blueprint $table): void {
            if (! Schema::hasColumn('shared_supplies', 'schedule_category_code')) {
                $table->string('schedule_category_code', 20)->nullable()->after('shared_supply_label_id');
                $table->index('schedule_category_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shared_supplies')) {
            return;
        }

        Schema::table('shared_supplies', function (Blueprint $table): void {
            if (Schema::hasColumn('shared_supplies', 'schedule_category_code')) {
                $table->dropColumn('schedule_category_code');
            }
        });
    }
};
