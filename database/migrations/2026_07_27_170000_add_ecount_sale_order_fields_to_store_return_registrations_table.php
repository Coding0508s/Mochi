<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('store_return_registrations', 'class_name')) {
                $table->string('class_name', 100)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('store_return_registrations', 'ecount_remarks')) {
                $table->string('ecount_remarks', 255)->nullable()->after('class_name');
            }
            if (! Schema::hasColumn('store_return_registrations', 'shipping_address')) {
                $table->string('shipping_address', 500)->nullable()->after('ecount_remarks');
            }
            if (! Schema::hasColumn('store_return_registrations', 'ecount_slip_no')) {
                $table->string('ecount_slip_no', 100)->nullable()->after('shipping_address');
            }
            if (! Schema::hasColumn('store_return_registrations', 'ecount_order_synced_at')) {
                $table->timestamp('ecount_order_synced_at')->nullable()->after('ecount_slip_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('store_return_registrations', 'class_name') ? 'class_name' : null,
                Schema::hasColumn('store_return_registrations', 'ecount_remarks') ? 'ecount_remarks' : null,
                Schema::hasColumn('store_return_registrations', 'shipping_address') ? 'shipping_address' : null,
                Schema::hasColumn('store_return_registrations', 'ecount_slip_no') ? 'ecount_slip_no' : null,
                Schema::hasColumn('store_return_registrations', 'ecount_order_synced_at') ? 'ecount_order_synced_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
