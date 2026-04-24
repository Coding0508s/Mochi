<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 신규 기관 생성 시 인원(LittleSEED / GrapeSEED 유·초) 저장용.
 * 기존 레거시 테이블에 컬럼이 없을 때만 추가합니다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('S_AccountName')) {
            return;
        }

        Schema::table('S_AccountName', function (Blueprint $table): void {
            if (! Schema::hasColumn('S_AccountName', 'LS')) {
                $table->unsignedInteger('LS')->default(0);
            }
            if (! Schema::hasColumn('S_AccountName', 'GS_K')) {
                $table->unsignedInteger('GS_K')->default(0);
            }
            if (! Schema::hasColumn('S_AccountName', 'GS_E')) {
                $table->unsignedInteger('GS_E')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('S_AccountName')) {
            return;
        }

        Schema::table('S_AccountName', function (Blueprint $table): void {
            if (Schema::hasColumn('S_AccountName', 'GS_E')) {
                $table->dropColumn('GS_E');
            }
            if (Schema::hasColumn('S_AccountName', 'GS_K')) {
                $table->dropColumn('GS_K');
            }
            if (Schema::hasColumn('S_AccountName', 'LS')) {
                $table->dropColumn('LS');
            }
        });
    }
};
