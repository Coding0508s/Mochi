<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('S_Account_Information')) {
            Schema::table('S_Account_Information', function (Blueprint $table): void {
                $this->addIndexIfMissing($table, 'S_Account_Information', 's_ai_sk_code_idx', ['SK_Code']);
                $this->addIndexIfMissing($table, 'S_Account_Information', 's_ai_co_idx', ['CO']);
                $this->addIndexIfMissing($table, 'S_Account_Information', 's_ai_tr_idx', ['TR']);
                $this->addIndexIfMissing($table, 'S_Account_Information', 's_ai_cs_idx', ['CS']);
                $this->addIndexIfMissing($table, 'S_Account_Information', 's_ai_fgc_create_date_idx', ['FGC_CreateDate']);
            });
        }

        if (Schema::hasTable('S_AccountName')) {
            Schema::table('S_AccountName', function (Blueprint $table): void {
                $this->addIndexIfMissing($table, 'S_AccountName', 's_an_skcode_idx', ['SKcode']);
                $this->addIndexIfMissing($table, 'S_AccountName', 's_an_gubun_idx', ['Gubun']);
            });
        }

        if (Schema::hasTable('S_GSNumber')) {
            Schema::table('S_GSNumber', function (Blueprint $table): void {
                $this->addIndexIfMissing($table, 'S_GSNumber', 's_gs_number_sk_code_idx', ['SKCode']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('S_Account_Information')) {
            Schema::table('S_Account_Information', function (Blueprint $table): void {
                $this->dropIndexIfExists($table, 'S_Account_Information', 's_ai_fgc_create_date_idx');
                $this->dropIndexIfExists($table, 'S_Account_Information', 's_ai_cs_idx');
                $this->dropIndexIfExists($table, 'S_Account_Information', 's_ai_tr_idx');
                $this->dropIndexIfExists($table, 'S_Account_Information', 's_ai_co_idx');
                $this->dropIndexIfExists($table, 'S_Account_Information', 's_ai_sk_code_idx');
            });
        }

        if (Schema::hasTable('S_AccountName')) {
            Schema::table('S_AccountName', function (Blueprint $table): void {
                $this->dropIndexIfExists($table, 'S_AccountName', 's_an_gubun_idx');
                $this->dropIndexIfExists($table, 'S_AccountName', 's_an_skcode_idx');
            });
        }

        if (Schema::hasTable('S_GSNumber')) {
            Schema::table('S_GSNumber', function (Blueprint $table): void {
                $this->dropIndexIfExists($table, 'S_GSNumber', 's_gs_number_sk_code_idx');
            });
        }
    }

    private function addIndexIfMissing(Blueprint $table, string $tableName, string $indexName, array $columns): void
    {
        if (! Schema::hasColumn($tableName, $columns[0]) || $this->hasIndex($tableName, $indexName)) {
            return;
        }

        $table->index($columns, $indexName);
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if (! $this->hasIndex($tableName, $indexName)) {
            return;
        }

        $table->dropIndex($indexName);
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `'.$tableName.'` WHERE Key_name = ?',
            [$indexName]
        );

        return $rows !== [];
    }
};
