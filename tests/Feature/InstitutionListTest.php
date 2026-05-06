<?php

namespace Tests\Feature;

use App\Jobs\SyncInstitutionOutboundJob;
use App\Livewire\InstitutionList;
use App\Models\GsNumber;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();
        $this->createAccountTables();
    }

    private function createAccountTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_GSNumber');
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

        Schema::create('S_GSNumber', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKCode', 100)->unique();
            $table->string('AccountName', 255)->nullable();
            $table->string('GSnumber', 100)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
        });

        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
        });

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->dateTime('Support_Date')->nullable();
            $table->integer('Year')->nullable();
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

    public function test_admin_in_co_team_can_see_all_institutions(): void
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

        $admin = User::factory()->create([
            'name' => 'Peter Kim Admin',
            'email' => 'peter.admin.co@grapeseed.com',
            'team' => 'CO',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
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
            ->test(InstitutionList::class)
            ->set('assignmentFilter', 'my_assigned')
            ->assertSee('내 담당 기관')
            ->assertDontSee('타 담당 기관');
    }

    public function test_list_prefers_gs_number_from_s_gs_number_table(): void
    {
        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-GS-LIST-1',
            'AccountName' => 'GS 표시 기관',
            'GSno' => '9.99',
        ]);

        GsNumber::query()->create([
            'SKCode' => 'SK-GS-LIST-1',
            'AccountName' => 'GS 표시 기관',
            'GSnumber' => '1.14',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->assertSee('1.14')
            ->assertDontSee('9.99');
    }

    public function test_save_detail_syncs_s_gs_number(): void
    {
        $user = User::factory()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-GS-SAVE-1',
            'AccountName' => '저장 테스트 기관',
            'GSno' => '1',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-GS-SAVE-1',
            'Account_Name' => '저장 테스트 기관',
            'CO' => 'CO One',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editGsNo', '2.5')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_GSNumber', [
            'SKCode' => 'SK-GS-SAVE-1',
            'GSnumber' => '2.5',
        ]);

        $this->assertDatabaseHas('S_AccountName', [
            'ID' => $institution->ID,
            'GSno' => '2.5',
        ]);
    }

    public function test_save_detail_updates_master_and_account_information(): void
    {
        $user = User::factory()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-FULL-1',
            'AccountName' => 'Old Name',
            'EnglishName' => 'Old En',
            'Director' => 'Dir',
            'Address' => 'Old Addr',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-FULL-1',
            'Account_Name' => 'Old Name',
            'CO' => 'C',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailInstitutionName', 'New Name')
            ->set('editDetailEnglishName', 'New En')
            ->set('editDetailDirector', 'New Dir')
            ->set('editDetailAddress', 'Addr 1')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'ID' => $institution->ID,
            'AccountName' => 'New Name',
            'EnglishName' => 'New En',
            'Director' => 'New Dir',
            'Address' => 'Addr 1',
        ]);
        $this->assertDatabaseHas('S_Account_Information', [
            'SK_Code' => 'SK-FULL-1',
            'Account_Name' => 'New Name',
            'Address' => 'Addr 1',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_save_managers_queues_outbound_when_enabled(): void
    {
        Config::set('services.institution_outbound.enabled', true);
        $user = User::factory()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-MANAGER-SYNC-1',
            'AccountName' => '담당자 동기화 테스트',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openManagerModal', $institution->ID)
            ->set('editCo', 'CO Sync')
            ->set('editTr', 'TR Sync')
            ->set('editCs', 'CS Sync')
            ->call('saveManagers')
            ->assertHasNoErrors();

        Queue::assertPushed(SyncInstitutionOutboundJob::class, function (SyncInstitutionOutboundJob $job): bool {
            return $job->sk === 'SK-MANAGER-SYNC-1';
        });
    }

    public function test_save_detail_fields_queues_outbound_when_enabled(): void
    {
        Config::set('services.institution_outbound.enabled', true);
        $user = User::factory()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-DETAIL-SYNC-1',
            'AccountName' => '상세 동기화 테스트',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailCo', 'CO Detail Sync')
            ->set('editDetailTr', 'TR Detail Sync')
            ->set('editDetailCs', 'CS Detail Sync')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        Queue::assertPushed(SyncInstitutionOutboundJob::class, function (SyncInstitutionOutboundJob $job): bool {
            return $job->sk === 'SK-DETAIL-SYNC-1';
        });
    }

    public function test_save_detail_sk_rename_cascades_sk_code_on_support_records(): void
    {
        $user = User::factory()->create();

        $institution = Institution::query()->create([
            'SKcode' => 'SK-OLD-X',
            'AccountName' => 'SK 변경 테스트',
        ]);

        DB::table('S_SupportInfo_Account')->insert([
            'SK_Code' => 'SK-OLD-X',
            'Year' => 2025,
            'Support_Date' => '2025-01-15',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionList::class)
            ->call('openDetailModal', $institution->ID)
            ->call('startDetailEdit')
            ->set('editDetailSkCode', 'SK-NEW-X')
            ->call('saveDetailFields')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_AccountName', [
            'ID' => $institution->ID,
            'SKcode' => 'SK-NEW-X',
        ]);
        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-NEW-X',
        ]);
        $this->assertDatabaseMissing('S_SupportInfo_Account', [
            'SK_Code' => 'SK-OLD-X',
        ]);
    }
}
