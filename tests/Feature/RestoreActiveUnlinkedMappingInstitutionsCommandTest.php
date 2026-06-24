<?php

namespace Tests\Feature;

use App\Models\Institution;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestoreActiveUnlinkedMappingInstitutionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100);
            $table->string('AccountName', 255)->nullable();
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('CO', 255)->nullable();
        });
    }

    public function test_apply_clears_terminated_customer_type_for_exception_sk_code(): void
    {
        DB::table('S_AccountName')->insert([
            'SKcode' => 'SK2664',
            'AccountName' => '천안 다우리숲키즈어린이집',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK2664',
            'Account_Name' => '천안 다우리숲키즈어린이집',
            'Customer_Type' => '해지',
            'CO' => 'James Kwak',
        ]);

        $this->artisan('institutions:restore-unlinked-mapping-active-exceptions', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK2664',
            'Customer_Type' => null,
            'CO' => 'James Kwak',
        ]);

        $institution = Institution::query()->where('SKcode', 'SK2664')->first();
        $this->assertNotNull($institution);
        $this->assertFalse($institution->fresh(['accountInfo'])->isTerminatedCustomer());
    }

    public function test_skips_non_exception_sk_code(): void
    {
        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-OTHER',
            'Account_Name' => '다른 기관',
            'Customer_Type' => '해지',
        ]);

        $this->artisan('institutions:restore-unlinked-mapping-active-exceptions', ['--apply' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-OTHER',
            'Customer_Type' => '해지',
        ]);
    }
}
