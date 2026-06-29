<?php

namespace Tests\Feature;

use App\Livewire\ContactList;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TeamMenuContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AdminMenuDataScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContactTables();
    }

    public function test_administration_team_sees_contacts_in_admin_sidebar_context(): void
    {
        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('EMAIL')->nullable();
        });

        Employee::query()->create([
            'EMPNO' => 'ADM010',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
            'KOREANAME' => 'Admin Staff',
            'EMAIL' => 'admin.staff@example.com',
        ]);

        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM010',
        ]);

        $this->seedInstitution('SK-ADMIN-1');
        Teacher::query()->create([
            'SK_Code' => 'SK-ADMIN-1',
            'Name' => 'Admin Context Teacher',
            'Email' => 'teacher@example.com',
            'ClassInOut' => true,
            'Status' => '활성화',
        ]);

        $this->actingAs($user)
            ->get('/contacts?sidebar_context=admin')
            ->assertOk()
            ->assertSee('Admin Context Teacher', false)
            ->assertDontSee('타 팀 메뉴에서는 조회만 가능합니다', false);
    }

    public function test_administration_team_without_admin_context_can_see_contacts(): void
    {
        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('EMAIL')->nullable();
        });

        Employee::query()->create([
            'EMPNO' => 'ADM011',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
            'KOREANAME' => 'Admin Staff 2',
            'EMAIL' => 'admin.staff2@example.com',
        ]);

        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM011',
        ]);

        $this->seedInstitution('SK-ADMIN-2');
        Teacher::query()->create([
            'SK_Code' => 'SK-ADMIN-2',
            'Name' => 'Hidden Outside Admin Context',
            'Email' => 'hidden@example.com',
            'ClassInOut' => true,
            'Status' => '활성화',
        ]);

        $this->actingAs($user)
            ->get('/contacts?team_menu=co')
            ->assertOk()
            ->assertSee('Hidden Outside Admin Context', false);
    }

    public function test_administration_team_can_search_contacts_in_admin_sidebar_context(): void
    {
        Schema::create('employee', function (Blueprint $table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('EMAIL')->nullable();
        });

        Employee::query()->create([
            'EMPNO' => 'ADM012',
            'WORKDEPT' => TeamMenuContext::DEPT_ADMINISTRATION,
            'KOREANAME' => 'Admin Search',
            'EMAIL' => 'admin.search@example.com',
        ]);

        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
            'employee_empno' => 'ADM012',
        ]);

        $this->seedInstitution('SK-ADMIN-SEARCH');
        Teacher::query()->create([
            'SK_Code' => 'SK-ADMIN-SEARCH',
            'Name' => '신현아',
            'Email' => 'sinhyunah@example.com',
            'ClassInOut' => true,
            'Status' => '활성화',
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['sidebar_context' => 'admin'])
            ->test(ContactList::class)
            ->set('searchType', 'name')
            ->set('search', '신현아')
            ->assertSee('신현아');
    }

    private function createContactTables(): void
    {
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');
        Schema::dropIfExists('employee');

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

        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 190)->nullable();
            $table->string('Email', 190)->nullable();
            $table->string('Phone', 190)->nullable();
            $table->string('Position', 100)->nullable();
            $table->string('School_Name', 255)->nullable();
            $table->boolean('ClassInOut')->nullable();
            $table->string('Status', 50)->nullable();
        });
    }

    private function seedInstitution(string $skCode): void
    {
        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => 'Test Institution',
        ]);
    }
}
