<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
        Schema::dropIfExists('employee');

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

        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('ENGLISHNAME')->nullable();
            $table->string('JOB')->nullable();
            $table->string('EMAIL')->nullable();
            $table->string('PHONENO')->nullable();
            $table->integer('STATUS')->nullable();
            $table->date('HIREDATE')->nullable();
        });
    }

    public function test_index_renders_and_has_no_inline_register_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('기관리스트')
            ->assertDontSee('신규 기관 등록');
    }

    public function test_legacy_open_create_query_does_not_show_create_ui(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('institutions.index', ['openCreate' => 1]))
            ->assertOk()
            ->assertDontSee('신규 기관 생성');
    }

    public function test_hidden_institution_is_not_visible_on_list(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-VISIBLE-1',
            'AccountName' => '표시 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-HIDDEN-1',
            'AccountName' => '숨김 기관',
        ]);

        DB::table('institution_visibility_overrides')->insert([
            'sk_code' => 'SK-HIDDEN-1',
            'hidden_reason' => 'uncontracted',
            'hidden_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('표시 기관')
            ->assertDontSee('숨김 기관');
    }

    public function test_co_team_user_sees_only_assigned_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CO-1',
            'AccountName' => '담당 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-CO-2',
            'AccountName' => '비담당 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-CO-1',
                'Account_Name' => '담당 기관',
                'CO' => 'Peter Kim',
            ],
            [
                'SK_Code' => 'SK-CO-2',
                'Account_Name' => '비담당 기관',
                'CO' => 'James Kwak',
            ],
        ]);

        $coUser = User::factory()->create([
            'name' => 'Peter Kim',
            'email' => 'peter.kim@grapeseed.com',
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $this->actingAs($coUser)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('담당 기관')
            ->assertDontSee('비담당 기관');
    }

    public function test_country_manager_in_co_team_can_see_all_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-CO-DM-1',
            'AccountName' => 'DM 담당 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-CO-DM-2',
            'AccountName' => 'DM 비담당 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-CO-DM-1',
                'Account_Name' => 'DM 담당 기관',
                'CO' => 'Peter Kim',
            ],
            [
                'SK_Code' => 'SK-CO-DM-2',
                'Account_Name' => 'DM 비담당 기관',
                'CO' => 'James Kwak',
            ],
        ]);

        DB::table('employee')->insert([
            'EMPNO' => 'DM100',
            'KOREANAME' => '컨트리매니저',
            'ENGLISHNAME' => 'Country Manager',
            'JOB' => 'CountryManager',
            'EMAIL' => 'dm100@grapeseed.com',
            'PHONENO' => '010-5555-5555',
            'WORKDEPT' => 'A02',
            'STATUS' => 1,
        ]);

        $countryManager = User::factory()->create([
            'name' => 'Country Manager User',
            'email' => 'dm100@grapeseed.com',
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $this->actingAs($countryManager)
            ->get(route('institutions.index'))
            ->assertOk()
            ->assertSee('DM 담당 기관')
            ->assertSee('DM 비담당 기관');
    }

    public function test_user_can_filter_only_my_assigned_institutions(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-MY-1',
            'AccountName' => '내 담당 기관',
        ]);
        Institution::query()->create([
            'SKcode' => 'SK-MY-2',
            'AccountName' => '타 담당 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-MY-1',
                'Account_Name' => '내 담당 기관',
                'CO' => 'Peter Kim',
            ],
            [
                'SK_Code' => 'SK-MY-2',
                'Account_Name' => '타 담당 기관',
                'CO' => 'James Kwak',
            ],
        ]);

        $user = User::factory()->create([
            'name' => 'Peter Kim',
            'email' => 'peter.kim@grapeseed.com',
            'team' => null,
            'is_admin' => false,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\InstitutionList::class)
            ->set('assignmentFilter', 'my_assigned')
            ->assertSee('내 담당 기관')
            ->assertDontSee('타 담당 기관');
    }
}
