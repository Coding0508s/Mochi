<?php

namespace Tests\Feature;

use App\Actions\SetTemporaryUserPassword;
use App\Livewire\PeopleEmployeesList;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTemporaryPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedEmployeeSchema();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        Gate::define('manageUserAccounts', fn (?User $user): bool => (bool) ($user?->hasFullAccess()));

        parent::tearDown();
    }

    public function test_admin_can_issue_temporary_password_for_active_linked_user(): void
    {
        $target = User::factory()->create([
            'employee_empno' => 'E001',
            'email' => 'e001@example.com',
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openTempPasswordModal', 'E001')
            ->assertSet('showTempPasswordConfirmModal', true)
            ->call('issueTemporaryPassword')
            ->assertSet('showTempPasswordResultModal', true);

        $target->refresh();
        $this->assertTrue((bool) $target->must_change_password);
        $this->assertNull($target->remember_token);
    }

    public function test_non_admin_cannot_issue_temporary_password(): void
    {
        User::factory()->create([
            'employee_empno' => 'E001',
            'email' => 'e001@example.com',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        Gate::define('manageUserAccounts', fn (?User $actor): bool => false);

        Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('openTempPasswordModal', 'E001')
            ->assertForbidden();
    }

    public function test_cannot_issue_temporary_password_for_self(): void
    {
        $admin = User::factory()->admin()->create([
            'employee_empno' => 'E001',
            'email' => 'e001@example.com',
        ]);

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openTempPasswordModal', 'E001')
            ->assertSet('showTempPasswordConfirmModal', false);
    }

    public function test_cannot_issue_temporary_password_for_inactive_user(): void
    {
        User::factory()->create([
            'employee_empno' => 'E001',
            'email' => 'e001@example.com',
            'is_active' => false,
        ]);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openTempPasswordModal', 'E001')
            ->assertSet('showTempPasswordConfirmModal', false);
    }

    public function test_cannot_issue_when_no_linked_account(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openTempPasswordModal', 'E001')
            ->assertSet('showTempPasswordConfirmModal', false);
    }

    public function test_admin_target_requires_extra_confirmation(): void
    {
        User::factory()->admin()->create([
            'employee_empno' => 'E001',
            'email' => 'e001@example.com',
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create([
            'employee_empno' => 'E999',
            'email' => 'admin-target@example.com',
        ]);

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openTempPasswordModal', 'E001')
            ->assertSet('tempPasswordTargetIsPrivileged', true)
            ->call('issueTemporaryPassword')
            ->assertSet('showTempPasswordResultModal', false);
    }

    public function test_temporary_password_allows_login(): void
    {
        $target = User::factory()->create([
            'employee_empno' => 'E001',
            'email' => 'e001@example.com',
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->call('openTempPasswordModal', 'E001')
            ->call('issueTemporaryPassword');

        $plainPassword = (string) $component->get('issuedTempPassword');
        $this->assertNotSame('', $plainPassword);

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'e001@example.com',
            'password' => $plainPassword,
        ])->assertRedirect(route('password.force-change'));

        $this->assertAuthenticatedAs($target->fresh());
    }

    public function test_audit_log_written_without_plaintext_password(): void
    {
        Log::spy();

        $target = User::factory()->create([
            'employee_empno' => 'E001',
            'email' => 'e001@example.com',
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create();

        app(SetTemporaryUserPassword::class)->execute($target, $admin);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('[admin] temporary password set', \Mockery::on(function (array $context): bool {
                return isset($context['admin_id'], $context['target_user_id'], $context['target_empno'])
                    && ! isset($context['password']);
            }));
    }

    public function test_user_with_must_change_password_is_redirected_to_force_change(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('institutions.index'))
            ->assertRedirect(route('password.force-change'));
    }

    public function test_force_change_clears_flag_and_allows_navigation(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'is_active' => true,
            'password' => Hash::make('temp-pass-1234'),
        ]);

        $this->actingAs($user)
            ->post(route('password.force-change.store'), [
                'password' => 'new-secure-password-1',
                'password_confirmation' => 'new-secure-password-1',
            ])
            ->assertRedirect(route('institutions.index'));

        $user->refresh();
        $this->assertFalse((bool) $user->must_change_password);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_force_change_validates_password_defaults(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('password.force-change.store'), [
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertTrue((bool) $user->must_change_password);
    }

    public function test_logout_allowed_while_must_change_password(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');
    }

    private function seedEmployeeSchema(): void
    {
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
                $table->string('SEX')->default('');
            });
        }
    }

    private function seedFixtures(): void
    {
        Department::query()->insert([
            ['DEPTNO' => 'A01', 'DEPTNAME' => '팀 A', 'ADMRDEPT' => '', 'LOCATION' => ''],
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
}
