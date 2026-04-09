<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 잠재기관(CO 신규 타깃) 레거시 테이블.
 * PotentialInstitutionList / CoNewTarget 모델과 tests/Feature/PotentialInstitutionListTest 스키마와 맞춥니다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('S_CO_NewTarget')) {
            Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
                $table->increments('ID');
                $table->integer('Year')->nullable();
                $table->date('CreatedDate')->nullable();
                $table->string('AccountManager', 100)->nullable();
                $table->string('AccountCode', 100)->nullable();
                $table->string('AccountName', 150);
                $table->string('Address', 255)->nullable();
                $table->string('Director', 100)->nullable();
                $table->string('Phone', 50)->nullable();
                $table->string('Connected', 100)->nullable();
                $table->string('Type', 100);
                $table->string('Gubun', 100);
                $table->integer('LS')->default(0);
                $table->integer('GS_K')->default(0);
                $table->integer('GS_E')->default(0);
                $table->integer('Total')->default(0);
                $table->integer('Approaching')->default(0);
                $table->integer('Presenting')->default(0);
                $table->integer('Consulting')->default(0);
                $table->integer('Closing')->default(0);
                $table->integer('DroppedOut')->default(0);
                $table->boolean('IsContract')->default(false);
                $table->date('ContractedDate')->nullable();
                $table->string('Possibility', 20)->nullable();
            });
        }

        if (! Schema::hasTable('S_CO_NewTarget_Detail')) {
            Schema::create('S_CO_NewTarget_Detail', function (Blueprint $table): void {
                $table->increments('ID');
                $table->integer('Year')->nullable();
                $table->string('AccountName', 150);
                $table->string('AccountManager', 100)->nullable();
                $table->date('MeetingDate');
                $table->string('MeetingTime', 20)->nullable();
                $table->string('MeetingTime_End', 20)->nullable();
                $table->text('Description')->nullable();
                $table->string('ConsultingType', 100)->nullable();
                $table->string('Possibility', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');
    }
};
