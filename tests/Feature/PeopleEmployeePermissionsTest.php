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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PeopleEmployeePermissionsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_without_country_manager_role_cannot_change_employee_department(): void
    {
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001');

        $this->assertNotTrue((bool) ($component->get('showEditModal') ?? false));
        $this->assertSame('A01', (string) Employee::query()->where('EMPNO', 'E001')->value('WORKDEPT'));
    }

    public function test_country_manager_can_change_employee_department(): void
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

        $countryManager = User::factory()->create([
            'email' => 'dm001@example.com',
            'is_admin' => false,
        ]);

        Livewire::actingAs($countryManager)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001')
            ->set('editWorkDept', 'A02')
            ->call('saveEmployee')
            ->assertHasNoErrors();

        $this->assertSame('A02', (string) Employee::query()->where('EMPNO', 'E001')->value('WORKDEPT'));
    }

    public function test_non_admin_cannot_open_create_team_modal(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $component = Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openCreateTeamModal');

        $this->assertNotTrue((bool) ($component->get('showCreateTeamModal') ?? false));
    }

    public function test_country_manager_can_open_create_team_modal(): void
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

        $countryManager = User::factory()->create([
            'email' => 'dm002@example.com',
            'is_admin' => false,
        ]);

        $component = Livewire::actingAs($countryManager)
            ->test(PeopleEmployeesList::class)
            ->call('openCreateTeamModal');

        $this->assertTrue((bool) ($component->get('showCreateTeamModal') ?? false));
    }

    public function test_country_manager_can_see_setup_related_sidebar_menus(): void
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

        $countryManager = User::factory()->create([
            'email' => 'dm003@example.com',
            'is_admin' => false,
        ]);

        $this->actingAs($countryManager)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('Review')
            ->assertSee('Goal')
            ->assertSee('Feedback')
            ->assertSee('Configuration')
            ->assertSee('Setup');
    }

    public function test_non_country_manager_cannot_see_setup_related_sidebar_menus(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('people.index'))
            ->assertOk()
            ->assertDontSee('>Review<', false)
            ->assertDontSee('>Goal<', false)
            ->assertDontSee('>Feedback<', false)
            ->assertDontSee('>Configuration<', false)
            ->assertDontSee('>Setup<', false);
    }

    public function test_admin_can_register_employee_via_setup(): void
    {
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

        $this->assertNull(User::query()->where('email', 'new@example.com')->first());
    }

    public function test_register_employee_with_login_account_creates_user_and_sends_reset_notification(): void
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
            ->set('issueLoginAccount', true)
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

    public function test_register_employee_with_login_account_can_assign_gs_brochure_admin_permission(): void
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
            ->set('issueLoginAccount', true)
            ->set('isGsBrochureAdmin', true)
            ->call('save')
            ->assertHasNoErrors();

        $newUser = User::query()->where('email', 'brochure.admin@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue((bool) $newUser->is_gs_brochure_admin);
    }

    public function test_admin_without_country_manager_role_cannot_update_linked_user_gs_brochure_permission_from_employee_modal(): void
    {
        $linkedUser = User::factory()->create([
            'email' => 'e001@example.com',
            'is_admin' => false,
            'is_gs_brochure_admin' => false,
        ]);

        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openEditModal', 'E001');

        $this->assertNotTrue((bool) ($component->get('showEditModal') ?? false));
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
            ->set('issueLoginAccount', true)
            ->call('save')
            ->assertHasErrors(['email']);

        $this->assertDatabaseMissing('employee', ['EMPNO' => 'E777']);
    }
}
