<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 레거시 Salesforce 파일 메타 테이블.
 * SalesforceFile 모델·SalesforceFileList Livewire와 컬럼명을 맞춥니다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('SF_Files')) {
            Schema::create('SF_Files', function (Blueprint $table): void {
                $table->increments('ID');
                $table->string('fileName', 1024)->nullable();
                $table->integer('download_Cnt')->nullable()->default(0);
                $table->string('LastUpdate_Date', 100)->nullable();
                $table->string('User', 255)->nullable();
                $table->string('created_Date', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('SF_Files');
    }
};
