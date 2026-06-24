<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institution_external_mappings')) {
            return;
        }

        Schema::create('institution_external_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('institution_id')->nullable();
            $table->string('institution_name', 100);
            $table->string('account_no', 32);
            $table->string('sk_code', 20);
            $table->string('erp_institution_name', 100);
            $table->string('erp_account_no', 32);
            $table->string('portal_campus_id', 36)->nullable();
            $table->timestamps();

            $table->unique('sk_code');
            $table->index('account_no');
            $table->index('erp_account_no');
            $table->index('portal_campus_id');
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_external_mappings');
    }
};
