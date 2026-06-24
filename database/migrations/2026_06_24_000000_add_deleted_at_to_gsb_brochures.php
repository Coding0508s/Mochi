<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('gs_brochure.table_prefix', 'gsb_');
        $brochures = $prefix.'brochures';

        Schema::table($brochures, function (Blueprint $table): void {
            if (! Schema::hasColumn($table->getTable(), 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        $prefix = (string) config('gs_brochure.table_prefix', 'gsb_');
        $brochures = $prefix.'brochures';

        Schema::table($brochures, function (Blueprint $table): void {
            if (Schema::hasColumn($table->getTable(), 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
