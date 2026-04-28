<?php

namespace Tests\Feature;

use App\Livewire\InstitutionCreateForm;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('features.institution_create_enabled', true);
        $this->createAccountTables();
    }

    private function createAccountTables(): void
    {
        Schema::dropIfExists('S_GSNumber');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('EnglishName', 255)->nullable();
            $table->string('PortalAccountName', 255)->nullable();
            $table->string('AccountNo', 100)->nullable();
            $table->string('GSno', 100)->nullable();
            $table->string('Director', 255)->nullable();
            $table->string('Phone', 100)->nullable();
            $table->string('AccountTel', 100)->nullable();
            $table->string('Address', 255)->nullable();
            $table->string('Gubun', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
            $table->unsignedInteger('LS')->default(0);
            $table->unsignedInteger('GS_K')->default(0);
            $table->unsignedInteger('GS_E')->default(0);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('Affiliate', 255)->nullable();
            $table->string('Address', 255)->nullable();
        });

        Schema::create('S_GSNumber', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKCode', 100)->unique();
            $table->string('AccountName', 255)->nullable();
            $table->string('GSnumber', 100)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
        });
    }

    public function test_create_page_renders_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.create'))
            ->assertOk()
            ->assertSee('신규 기관 생성')
            ->assertSee('인원 정보')
            ->assertSee('LittleSEED');
    }

    public function test_create_page_redirects_when_feature_disabled(): void
    {
        Config::set('features.institution_create_enabled', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.create'))
            ->assertRedirect(route('institutions.index'))
            ->assertSessionHas('warning');
    }

    public function test_save_new_institution_stores_possibility(): void
    {
        $user = User::factory()->create();
        $sk = 'SK-TEST-'.uniqid();

        Livewire::actingAs($user)
            ->test(InstitutionCreateForm::class)
            ->set('newSkCode', $sk)
            ->set('newInstitutionName', '테스트 유치원')
            ->set('newPossibility', 'C')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('institutions.index'));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '테스트 유치원',
            'Possibility' => 'C',
        ]);
    }

    public function test_save_new_institution_nullable_possibility(): void
    {
        $user = User::factory()->create();
        $sk = 'SK-TEST-'.uniqid();

        Livewire::actingAs($user)
            ->test(InstitutionCreateForm::class)
            ->set('newSkCode', $sk)
            ->set('newInstitutionName', '무가능성 테스트')
            ->set('newPossibility', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'Possibility' => null,
        ]);
    }

    public function test_save_new_institution_stores_headcount_fields(): void
    {
        $user = User::factory()->create();
        $sk = 'SK-HC-'.uniqid();

        Livewire::actingAs($user)
            ->test(InstitutionCreateForm::class)
            ->set('newSkCode', $sk)
            ->set('newInstitutionName', '인원 테스트 기관')
            ->set('newLS', '10')
            ->set('newGSK', '20')
            ->set('newGSE', '5')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('institutions.index'));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'LS' => 10,
            'GS_K' => 20,
            'GS_E' => 5,
        ]);
    }

    public function test_save_new_institution_writes_s_gs_number_when_gs_no_set(): void
    {
        $user = User::factory()->create();
        $sk = 'SK-GS-CREATE-'.uniqid();

        Livewire::actingAs($user)
            ->test(InstitutionCreateForm::class)
            ->set('newSkCode', $sk)
            ->set('newInstitutionName', 'GS 동기화 기관')
            ->set('newGsNo', '3.21')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('institutions.index'));

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'GSno' => '3.21',
        ]);

        $this->assertDatabaseHas('S_GSNumber', [
            'SKCode' => $sk,
            'GSnumber' => '3.21',
            'AccountName' => 'GS 동기화 기관',
        ]);
    }
}
