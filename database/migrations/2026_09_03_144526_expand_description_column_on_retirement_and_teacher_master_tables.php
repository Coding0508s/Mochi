<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'S_RetirementList',
        'S_TeacherMasterDB',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            $this->changeDescription($tableName, function (Blueprint $table): void {
                $table->text('Description')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            $this->changeDescription($tableName, function (Blueprint $table): void {
                $table->string('Description', 255)->nullable()->change();
            });
        }
    }

    private function changeDescription(string $tableName, callable $callback): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'Description')) {
            return;
        }

        Schema::table($tableName, $callback);
    }
};
