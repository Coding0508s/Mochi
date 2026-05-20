<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillInstitutionAccountNamesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
    }

    private function createTables(): void
    {
        Schema::dropIfExists('S_GSNumber');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
        });

        Schema::create('S_GSNumber', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKCode', 100)->unique();
            $table->string('AccountName', 255)->nullable();
            $table->string('GSnumber', 100)->nullable();
        });
    }

    public function test_dry_run_does_not_update_account_name(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-BACKFILL-DRY',
            'AccountName' => '레거시 기관명',
        ]);
        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-BACKFILL-DRY',
            'Account_Name' => '마스터 기관명',
        ]);

        $this->artisan('institutions:backfill-account-names')
            ->assertSuccessful();

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-BACKFILL-DRY',
            'AccountName' => '레거시 기관명',
        ]);
    }

    public function test_apply_updates_account_name_and_gs_number_name(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-BACKFILL-APPLY',
            'AccountName' => '레거시 기관명',
        ]);
        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-BACKFILL-APPLY',
            'Account_Name' => '마스터 기관명',
        ]);
        DB::table('S_GSNumber')->insert([
            'SKCode' => 'SK-BACKFILL-APPLY',
            'AccountName' => '레거시 기관명',
            'GSnumber' => '1.1',
        ]);

        $this->artisan('institutions:backfill-account-names', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => 'SK-BACKFILL-APPLY',
            'AccountName' => '마스터 기관명',
        ]);
        $this->assertDatabaseHas('S_GSNumber', [
            'SKCode' => 'SK-BACKFILL-APPLY',
            'AccountName' => '마스터 기관명',
        ]);
    }
}
