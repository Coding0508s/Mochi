<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR department: Training 팀 표시명을 Coach 로 통일 (DEPTNO A05).
     */
    public function up(): void
    {
        if (! Schema::hasTable('department')) {
            return;
        }

        DB::table('department')
            ->where('DEPTNO', 'A05')
            ->update(['DEPTNAME' => 'Coach']);

        DB::table('department')
            ->whereRaw('TRIM(COALESCE(DEPTNAME, \'\')) = ?', ['Training'])
            ->update(['DEPTNAME' => 'Coach']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('department')) {
            return;
        }

        DB::table('department')
            ->where('DEPTNO', 'A05')
            ->where('DEPTNAME', 'Coach')
            ->update(['DEPTNAME' => 'Training']);
    }
};
