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
        if (! Schema::hasTable('vehicle_usage_logs')) {
            return;
        }

        Schema::table('vehicle_usage_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_usage_logs', 'shared_supply_id')) {
                $table->foreignId('shared_supply_id')->nullable()->after('id')->constrained('shared_supplies')->nullOnDelete();
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('shared_supply_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'vehicle_name')) {
                $table->string('vehicle_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'usage_purpose_name')) {
                $table->string('usage_purpose_name')->nullable()->after('vehicle_name');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'odometer_before')) {
                $table->unsignedInteger('odometer_before')->nullable()->after('usage_purpose_name');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'odometer_after')) {
                $table->unsignedInteger('odometer_after')->nullable()->after('odometer_before');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'distance')) {
                $table->unsignedInteger('distance')->nullable()->after('odometer_after');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'arrival_location')) {
                $table->string('arrival_location')->nullable()->after('distance');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'remarks')) {
                $table->string('remarks')->nullable()->after('arrival_location');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'driven_on')) {
                $table->date('driven_on')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('driven_on');
            }
            if (! Schema::hasColumn('vehicle_usage_logs', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('vehicle_usage_logs')) {
            return;
        }

        Schema::table('vehicle_usage_logs', function (Blueprint $table): void {
            $dropColumns = [
                'updated_by',
                'created_by',
                'driven_on',
                'remarks',
                'arrival_location',
                'distance',
                'odometer_after',
                'odometer_before',
                'usage_purpose_name',
                'vehicle_name',
                'user_id',
                'shared_supply_id',
            ];

            foreach ($dropColumns as $column) {
                if (! Schema::hasColumn('vehicle_usage_logs', $column)) {
                    continue;
                }

                if (in_array($column, ['shared_supply_id', 'user_id'], true)) {
                    $table->dropConstrainedForeignId($column);

                    continue;
                }

                $table->dropColumn($column);
            }
        });
    }
};
