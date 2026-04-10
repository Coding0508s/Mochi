<?php

namespace Tests\Feature;

use App\Livewire\InstitutionList;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionListTest extends TestCase
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
    }

    public function test_open_create_query_opens_modal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.index', ['openCreate' => 1]))
            ->assertOk()
            ->assertSee('신규 기관 생성');
    }

    public function test_save_new_institution_stores_possibility(): void
    {
        $user = User::factory()->create();
        $sk = 'SK-TEST-'.uniqid();

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openCreateModal')
            ->set('newSkCode', $sk)
            ->set('newInstitutionName', '테스트 유치원')
            ->set('newPossibility', 'C')
            ->call('saveNewInstitution')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'AccountName' => '테스트 유치원',
            'Possibility' => 'C',
        ]);
    }

    public function test_open_create_query_does_not_open_modal_when_feature_disabled(): void
    {
        Config::set('features.institution_create_enabled', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.index', ['openCreate' => 1]))
            ->assertOk()
            ->assertDontSee('신규 기관 생성');
    }

    public function test_save_new_institution_nullable_possibility(): void
    {
        $user = User::factory()->create();
        $sk = 'SK-TEST-'.uniqid();

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openCreateModal')
            ->set('newSkCode', $sk)
            ->set('newInstitutionName', '무가능성 테스트')
            ->set('newPossibility', '')
            ->call('saveNewInstitution')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'SKcode' => $sk,
            'Possibility' => null,
        ]);
    }
}
