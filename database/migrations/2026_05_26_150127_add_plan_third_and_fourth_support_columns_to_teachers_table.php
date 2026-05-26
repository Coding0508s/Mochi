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
        if (! Schema::hasTable('Teachers')) {
            return;
        }

        Schema::table('Teachers', function (Blueprint $table): void {
            if (! Schema::hasColumn('Teachers', 'Plan_3rd_Support_Date')) {
                $table->date('Plan_3rd_Support_Date')->nullable()->after('Plan_2nd_Support_Type');
            }

            if (! Schema::hasColumn('Teachers', 'Plan_3rd_Support_Type')) {
                $table->string('Plan_3rd_Support_Type', 100)->nullable()->after('Plan_3rd_Support_Date');
            }

            if (! Schema::hasColumn('Teachers', 'Plan_4th_Support_Date')) {
                $table->date('Plan_4th_Support_Date')->nullable()->after('Plan_3rd_Support_Type');
            }

            if (! Schema::hasColumn('Teachers', 'Plan_4th_Support_Type')) {
                $table->string('Plan_4th_Support_Type', 100)->nullable()->after('Plan_4th_Support_Date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('Teachers')) {
            return;
        }

        Schema::table('Teachers', function (Blueprint $table): void {
            $columns = [
                'Plan_4th_Support_Type',
                'Plan_4th_Support_Date',
                'Plan_3rd_Support_Type',
                'Plan_3rd_Support_Date',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('Teachers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
