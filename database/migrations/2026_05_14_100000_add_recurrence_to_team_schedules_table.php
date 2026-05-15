<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_schedules', function (Blueprint $table): void {
            $table->string('recurrence_rule', 20)->nullable()->after('location');
            $table->foreignId('recurrence_parent_id')
                ->nullable()
                ->after('recurrence_rule')
                ->constrained('team_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn('recurrence_rule');
        });
    }
};
