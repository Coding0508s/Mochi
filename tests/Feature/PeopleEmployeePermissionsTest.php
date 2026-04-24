<?php

namespace Tests\Feature;

use App\Livewire\PeopleEmployeesList;
use App\Livewire\SetupEmployeeCreate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PeopleEmployeePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Gate::define('manageUserAccounts', fn (?User $user): bool => (bool) ($user?->hasFullAccess()));

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('department')) {
            Schema::create('department', function (Blueprint $table): void {
                $table->string('DEPTNO')->primary();
                $table->string('DEPTNAME')->nullable();
                $table->string('ADMRDEPT')->nullable();
                $table->string('LOCATION')->nullable();
            });
        }

        if (! Schema::hasTable('employee')) {
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

        Department::query()->insert([
            ['DEPTNO' => 'A01', 'DEPTNAME' => '팀 A', 'ADMRDEPT' => '', 'LOCATION' => ''],
            ['DEPTNO' => 'A02', 'DEPTNAME' => '팀 B', 'ADMRDEPT' => '', 'LOCATION' => ''],
        ]);

        Employee::query()->create([
            'EMPNO' => 'E001',
            'KOREANAME' => '홍길동',
            'ENGLISHNAME' => 'Hong',
            'JOB' => '매니저',
            'EMAIL' => 'e001@example.com',
            'PHONENO' => '010-0000-0000',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);
    }

    public function test_non_admin_cannot_change_employee_department(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $component = Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001');

        $this->assertNotTrue((bool) ($component->get('showEditModal') ?? false));
        $this->assertSame('A01', (string) Employee::query()->where('EMPNO', 'E001')->value('WORKDEPT'));
    }

    public function test_non_admin_cannot_open_employee_edit_modal(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $component = Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001');

        $this->assertNotTrue((bool) ($component->get('showEditModal') ?? false));
    }

    public function test_admin_can_open_edit_modal_and_change_employee_department(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001')
            ->assertSet('showEditModal', true)
            ->set('editWorkDept', 'A02')
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertSame('A02', (string) Employee::query()->where('EMPNO', 'E001')->value('WORKDEPT'));
    }

    public function test_country_manager_job_employee_without_admin_cannot_open_edit_modal(): void
    {
        Employee::query()->create([
            'EMPNO' => 'DM001',
            'KOREANAME' => '컨트리매니저',
            'ENGLISHNAME' => 'Country Manager',
            'JOB' => 'CountryManager',
            'EMAIL' => 'dm001@example.com',
            'PHONENO' => '010-9999-0000',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $user = User::factory()->create([
            'email' => 'dm001@example.com',
            'is_admin' => false,
        ]);

        $component = Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001');

        $this->assertNotTrue((bool) ($component->get('showEditModal') ?? false));
        $this->assertSame('A01', (string) Employee::query()->where('EMPNO', 'E001')->value('WORKDEPT'));
    }

    public function test_non_admin_cannot_open_create_team_modal(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $component = Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openCreateTeamModal');

        $this->assertNotTrue((bool) ($component->get('showCreateTeamModal') ?? false));
    }

    public function test_country_manager_job_user_without_admin_cannot_open_create_team_modal(): void
    {
        Employee::query()->create([
            'EMPNO' => 'DM002',
            'KOREANAME' => '컨트리매니저2',
            'ENGLISHNAME' => 'Country Manager Two',
            'JOB' => 'CountryManager',
            'EMAIL' => 'dm002@example.com',
            'PHONENO' => '010-9999-0001',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $user = User::factory()->create([
            'email' => 'dm002@example.com',
            'is_admin' => false,
        ]);

        $component = Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openCreateTeamModal');

        $this->assertNotTrue((bool) ($component->get('showCreateTeamModal') ?? false));
    }

    public function test_country_manager_job_user_without_admin_cannot_see_setup_related_sidebar_menus(): void
    {
        Employee::query()->create([
            'EMPNO' => 'DM003',
            'KOREANAME' => '컨트리매니저3',
            'ENGLISHNAME' => 'Country Manager Three',
            'JOB' => 'CountryManager',
            'EMAIL' => 'dm003@example.com',
            'PHONENO' => '010-9999-0003',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $user = User::factory()->create([
            'email' => 'dm003@example.com',
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertDontSee('>Review<', false)
            ->assertDontSee('>Goal<', false)
            ->assertDontSee('>Feedback<', false)
            ->assertDontSee('>Configuration<', false)
            ->assertDontSee('>Setup<', false);
    }

    public function test_non_country_manager_cannot_see_setup_related_sidebar_menus(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertDontSee('>Review<', false)
            ->assertDontSee('>Goal<', false)
            ->assertDontSee('>Feedback<', false)
            ->assertDontSee('>Configuration<', false)
            ->assertDontSee('>Setup<', false);
    }

    public function test_admin_can_see_setup_related_sidebar_menus(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('Review')
            ->assertSee('Goal')
            ->assertSee('Feedback')
            ->assertSee('Configuration')
            ->assertSee('Setup');
    }

    public function test_admin_can_see_employee_register_button_on_people_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('직원 등록');
    }

    public function test_non_admin_cannot_see_employee_register_button_on_people_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('people.index'))
            ->assertOk()
            ->assertDontSee('직원 등록');
    }

    public function test_admin_can_open_employee_register_modal_from_people_page(): void
    {
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openCreateEmployeeModal');

        $this->assertTrue((bool) ($component->get('showCreateEmployeeModal') ?? false));
    }

    public function test_admin_can_register_employee_via_people_modal(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openCreateEmployeeModal')
            ->set('createEmpNo', 'E996')
            ->set('createKoreanName', '모달신규')
            ->set('createEnglishName', 'Modal New')
            ->set('createJob', '매니저')
            ->set('createEmail', 'modal-new@example.com')
            ->set('createPhone', '010-1111-9999')
            ->set('createWorkDept', 'A01')
            ->set('createStatus', '1')
            ->call('createEmployee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee', [
            'EMPNO' => 'E996',
            'WORKDEPT' => 'A01',
            'EMAIL' => 'modal-new@example.com',
        ]);

        $newUser = User::query()->where('email', 'modal-new@example.com')->first();
        $this->assertNotNull($newUser);
        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_admin_can_register_employee_via_setup(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupEmployeeCreate::class)
            ->set('empNo', 'E999')
            ->set('koreanName', '신규')
            ->set('englishName', 'New Hire')
            ->set('job', '매니저')
            ->set('email', 'new@example.com')
            ->set('phone', '010-1111-2222')
            ->set('workDept', 'A01')
            ->set('status', '1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('people.index', [], false));

        $this->assertDatabaseHas('employee', [
            'EMPNO' => 'E999',
            'WORKDEPT' => 'A01',
            'EMAIL' => 'new@example.com',
        ]);

        $newUser = User::query()->where('email', 'new@example.com')->first();
        $this->assertNotNull($newUser);
        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_register_employee_via_setup_always_creates_user_and_sends_reset_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupEmployeeCreate::class)
            ->set('empNo', 'E888')
            ->set('koreanName', '계정')
            ->set('englishName', 'Account User')
            ->set('job', '매니저')
            ->set('email', 'account.issue@example.com')
            ->set('phone', '010-3333-4444')
            ->set('workDept', 'A01')
            ->set('status', '1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('people.index', [], false));

        $this->assertDatabaseHas('employee', [
            'EMPNO' => 'E888',
            'EMAIL' => 'account.issue@example.com',
        ]);

        $newUser = User::query()->where('email', 'account.issue@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertFalse($newUser->is_admin);

        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_register_employee_via_setup_can_assign_gs_brochure_admin_permission(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupEmployeeCreate::class)
            ->set('empNo', 'E889')
            ->set('koreanName', '브로셔권한')
            ->set('englishName', 'Brochure Admin')
            ->set('job', '매니저')
            ->set('email', 'brochure.admin@example.com')
            ->set('phone', '010-7777-9999')
            ->set('workDept', 'A01')
            ->set('status', '1')
            ->set('isGsBrochureAdmin', true)
            ->call('save')
            ->assertHasNoErrors();

        $newUser = User::query()->where('email', 'brochure.admin@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue((bool) $newUser->is_gs_brochure_admin);
    }

    public function test_admin_can_open_edit_modal_for_linked_employee_without_changing_gs_brochure_when_gate_disabled(): void
    {
        $linkedUser = User::factory()->create([
            'email' => 'e001@example.com',
            'is_admin' => false,
            'is_gs_brochure_admin' => false,
        ]);

        $admin = User::factory()->admin()->create();

        Gate::define('manageUserAccounts', fn (?User $user): bool => false);

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001')
            ->assertSet('showEditModal', true)
            ->assertDontSee('계정 권한')
            ->set('editUserIsAdmin', true)
            ->set('editGsBrochureAdmin', true)
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $linkedUser->id,
            'is_gs_brochure_admin' => false,
        ]);
    }

    public function test_non_admin_cannot_update_linked_user_gs_brochure_permission_from_employee_modal(): void
    {
        $linkedUser = User::factory()->create([
            'email' => 'e001@example.com',
            'is_admin' => false,
            'is_gs_brochure_admin' => false,
        ]);

        $user = User::factory()->create(['is_admin' => false]);

        $component = Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001');

        $this->assertNotTrue((bool) ($component->get('showEditModal') ?? false));
        $this->assertDatabaseHas('users', [
            'id' => $linkedUser->id,
            'is_gs_brochure_admin' => false,
        ]);
    }

    public function test_register_employee_with_login_account_rejects_duplicate_user_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SetupEmployeeCreate::class)
            ->set('empNo', 'E777')
            ->set('koreanName', '중복')
            ->set('englishName', 'Dup')
            ->set('job', '매니저')
            ->set('email', 'taken@example.com')
            ->set('phone', '010-0000-0001')
            ->set('workDept', 'A01')
            ->set('status', '1')
            ->call('save')
            ->assertHasErrors(['email']);

        $this->assertDatabaseMissing('employee', ['EMPNO' => 'E777']);
    }

    public function test_user_without_manage_user_accounts_gate_cannot_see_or_save_account_checkboxes_from_employee_modal(): void
    {
        $linkedUser = User::factory()->create([
            'employee_empno' => 'E001',
            'is_active' => true,
            'is_admin' => false,
            'is_gs_brochure_admin' => false,
        ]);

        $admin = User::factory()->admin()->create();

        Gate::define('manageUserAccounts', fn (?User $user): bool => false);

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001')
            ->assertSet('hasLinkedLoginAccount', true)
            ->assertDontSee('계정 권한')
            ->set('editUserIsAdmin', true)
            ->set('editGsBrochureAdmin', true)
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $linkedUser->id,
            'is_active' => true,
            'is_admin' => false,
            'is_gs_brochure_admin' => false,
        ]);
    }

    public function test_user_with_manage_user_accounts_gate_can_save_account_checkboxes_from_employee_modal(): void
    {
        $linkedUser = User::factory()->create([
            'employee_empno' => 'E001',
            'is_active' => true,
            'is_admin' => false,
            'is_gs_brochure_admin' => false,
        ]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001')
            ->assertSet('hasLinkedLoginAccount', true)
            ->assertSee('계정 권한')
            ->set('editStatus', '0')
            ->set('editUserIsAdmin', true)
            ->set('editGsBrochureAdmin', true)
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $linkedUser->id,
            'is_active' => false,
            'is_admin' => true,
            'is_gs_brochure_admin' => true,
        ]);
    }

    public function test_user_cannot_deactivate_own_account_from_employee_modal(): void
    {
        Employee::query()->create([
            'EMPNO' => 'DM020',
            'KOREANAME' => '본인',
            'ENGLISHNAME' => 'Self User',
            'JOB' => 'CountryManager',
            'EMAIL' => 'dm020@example.com',
            'PHONENO' => '010-2222-0000',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $actor = User::factory()->admin()->create([
            'email' => 'dm020@example.com',
            'employee_empno' => 'DM020',
            'is_active' => true,
        ]);

        Livewire::actingAs($actor)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'DM020')
            ->assertSet('hasLinkedLoginAccount', true)
            ->set('editStatus', '0')
            ->call('saveEmployee')
            ->assertHasErrors(['editStatus']);

        $this->assertDatabaseHas('users', [
            'id' => $actor->id,
            'is_active' => true,
        ]);
    }

    public function test_last_active_admin_cannot_remove_own_admin_flag_from_employee_modal(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E002',
            'KOREANAME' => '관리자',
            'ENGLISHNAME' => 'Only Admin',
            'JOB' => 'Manager',
            'EMAIL' => 'only-admin@example.com',
            'PHONENO' => '010-2222-0001',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $onlyAdmin = User::factory()->create([
            'email' => 'only-admin@example.com',
            'employee_empno' => 'E002',
            'is_active' => true,
            'is_admin' => true,
        ]);

        User::query()->whereKeyNot($onlyAdmin->id)->update(['is_admin' => false]);

        Livewire::actingAs($onlyAdmin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E002')
            ->assertSet('hasLinkedLoginAccount', true)
            ->set('editUserIsAdmin', false)
            ->call('saveEmployee')
            ->assertHasErrors(['editUserIsAdmin']);

        $this->assertDatabaseHas('users', [
            'id' => $onlyAdmin->id,
            'is_active' => true,
            'is_admin' => true,
        ]);
    }

    public function test_people_modal_prefers_employee_empno_link_before_email_fallback(): void
    {
        config()->set('features.people_account_email_fallback_enabled', true);

        $linkedByEmpNo = User::factory()->create([
            'email' => 'another-email@example.com',
            'employee_empno' => 'E001',
        ]);

        User::factory()->create([
            'email' => 'e001@example.com',
            'employee_empno' => 'E999',
        ]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001')
            ->assertSet('linkedUserId', $linkedByEmpNo->id);
    }

    public function test_people_modal_auto_creates_login_account_when_missing(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001')
            ->assertSet('hasLinkedLoginAccount', false)
            ->set('editUserIsAdmin', false)
            ->set('editGsBrochureAdmin', true)
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $createdUser = User::query()->where('employee_empno', 'E001')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame('e001@example.com', $createdUser->email);
        $this->assertTrue((bool) $createdUser->is_gs_brochure_admin);

        Notification::assertSentTo($createdUser, ResetPassword::class);
    }
}
