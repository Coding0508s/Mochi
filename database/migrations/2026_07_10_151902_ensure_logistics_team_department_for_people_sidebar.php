<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People 사이드바는 department 테이블을 그대로 노출한다.
     * Logistics Team 부서가 없으면 People 메뉴에 표시되지 않는다.
     */
    public function up(): void
    {
        if (! Schema::hasTable('department')) {
            return;
        }

        $alreadyExists = DB::table('department')
            ->whereRaw('TRIM(COALESCE(DEPTNAME, \'\')) = ?', ['Logistics Team'])
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $preferredDeptNo = 'A04';
        $deptNo = DB::table('department')->where('DEPTNO', $preferredDeptNo)->exists()
            ? $this->nextAvailableDeptNo()
            : $preferredDeptNo;

        DB::table('department')->insert([
            'DEPTNO' => $deptNo,
            'DEPTNAME' => 'Logistics Team',
            'MGRNO' => '',
            'ADMRDEPT' => '',
            'LOCATION' => '',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('department')) {
            return;
        }

        DB::table('department')
            ->whereRaw('TRIM(COALESCE(DEPTNAME, \'\')) = ?', ['Logistics Team'])
            ->delete();
    }

    private function nextAvailableDeptNo(): string
    {
        $maxNumber = DB::table('department')
            ->where('DEPTNO', 'like', 'A%')
            ->pluck('DEPTNO')
            ->map(function (string $deptNo): ?int {
                if (! preg_match('/^A(\d+)$/', $deptNo, $matches)) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter()
            ->max() ?? 0;

        return 'A'.str_pad((string) ($maxNumber + 1), 2, '0', STR_PAD_LEFT);
    }
};
