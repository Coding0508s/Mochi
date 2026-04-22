<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('EMAIL')->nullable();
                $table->string('ENGLISHNAME')->nullable();
            });
        }
    }

    private function createUserWithEmployee(array $attributes = [], string $englishName = 'DEFAULT ENGLISH'): User
    {
        $user = User::factory()->create($attributes);
        Employee::create([
            'EMPNO' => 'EMP'.$user->id,
            'EMAIL' => $user->email,
            'ENGLISHNAME' => $englishName,
        ]);

        return $user;
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->createUserWithEmployee();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('영어 이름', false);
        $response->assertDontSee('Delete Account');
    }

    public function test_admin_profile_page_shows_delete_account_section(): void
    {
        $admin = $this->createUserWithEmployee(['is_admin' => true]);

        $response = $this
            ->actingAs($admin)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('Delete Account');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->createUserWithEmployee([], 'OLD ENGLISH');
        $originalEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'english_name' => 'NEW ENGLISH',
                'email' => $originalEmail,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('NEW ENGLISH', Employee::query()->where('EMPNO', 'EMP'.$user->id)->value('ENGLISHNAME'));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createUserWithEmployee([], 'UNCHANGED ENGLISH');

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'english_name' => 'UNCHANGED ENGLISH',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_update_fails_when_email_is_changed(): void
    {
        $user = $this->createUserWithEmployee([], 'BEFORE ENGLISH');
        $originalName = $user->name;
        $originalEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Email Changed User',
                'english_name' => 'AFTER ENGLISH',
                'email' => 'changed-email@example.com',
            ]);

        $response
            ->assertSessionHasErrors([
                'email' => '이메일은 프로필에서 변경할 수 없습니다. 변경이 필요하면 관리자에게 요청해 주세요.',
            ])
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame($originalName, $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertSame('BEFORE ENGLISH', Employee::query()->where('EMPNO', 'EMP'.$user->id)->value('ENGLISHNAME'));
    }

    public function test_profile_update_fails_when_email_field_is_missing(): void
    {
        $user = $this->createUserWithEmployee([], 'INITIAL ENGLISH');
        $originalName = $user->name;
        $originalEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Missing Email User',
                'english_name' => 'UPDATED ENGLISH',
            ]);

        $response
            ->assertSessionHasErrors([
                'email' => '이메일은 프로필에서 변경할 수 없습니다. 화면을 새로고침한 뒤 다시 시도해 주세요.',
            ])
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame($originalName, $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertSame('INITIAL ENGLISH', Employee::query()->where('EMPNO', 'EMP'.$user->id)->value('ENGLISHNAME'));
    }

    public function test_profile_update_fails_when_matching_employee_is_missing(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Missing Employee',
                'english_name' => 'NO MATCH',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasErrors(['english_name'])
            ->assertRedirect('/profile');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->createUserWithEmployee(['is_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->createUserWithEmployee(['is_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_non_admin_user_cannot_delete_account(): void
    {
        $user = $this->createUserWithEmployee();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertForbidden();
        $this->assertNotNull($user->fresh());
    }
}
