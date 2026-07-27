<?php

namespace Tests\Feature;

use App\Livewire\ContactList;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ContactListWritePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createContactTables();
    }

    public function test_non_manager_user_is_read_only_for_contact_writes(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'name' => 'Read Only User',
        ]);

        $this->seedTeacher('SK-READ-ONLY', '담당자 없는 기관', 'Another Manager', '읽기전용 교사');

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->assertDontSee('신규 생성')
            ->call('openDetailModal', Teacher::query()->value('ID'))
            ->assertDontSeeHtml('wire:click="openEditFromDetail"');

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('newName', '권한 없음')
            ->set('newEmail', 'readonly@example.com')
            ->set('newSkCode', 'SK-READ-ONLY')
            ->set('newSchoolName', '담당자 없는 기관')
            ->call('save')
            ->assertForbidden();
    }

    public function test_assigned_co_manager_can_create_and_update_contacts(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'name' => 'Allowed Manager',
        ]);

        $this->seedInstitutionWithCo('SK-MANAGED', '관리 가능 기관', 'Allowed Manager');

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openCreateModal')
            ->set('newName', '신규 담당 교사')
            ->set('newEmail', 'managed-create@example.com')
            ->set('newSkCode', 'SK-MANAGED')
            ->set('newSchoolName', '관리 가능 기관')
            ->set('newClassParticipation', 'in')
            ->call('save')
            ->assertHasNoErrors();

        $teacher = Teacher::query()->where('Email', 'managed-create@example.com')->first();
        $this->assertNotNull($teacher);

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openEditModal', $teacher->ID)
            ->set('newName', '수정된 담당 교사')
            ->call('save')
            ->assertHasNoErrors();

        $teacher->refresh();
        $this->assertSame('수정된 담당 교사', $teacher->Name);
    }

    public function test_assigned_manager_cannot_create_or_move_contact_to_unmanaged_institution(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'name' => 'Scoped Manager',
        ]);

        $this->seedInstitutionWithCo('SK-MANAGED-2', '관리 가능 기관', 'Scoped Manager');
        $this->seedInstitutionWithCo('SK-UNMANAGED', '관리 불가 기관', 'Someone Else');

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openCreateModal')
            ->set('newName', '생성 실패 교사')
            ->set('newEmail', 'cannot-create@example.com')
            ->set('newSkCode', 'SK-UNMANAGED')
            ->set('newSchoolName', '관리 불가 기관')
            ->call('save')
            ->assertForbidden();

        $teacher = Teacher::query()->create([
            'SK_Code' => 'SK-MANAGED-2',
            'Name' => '기존 담당 교사',
            'Email' => 'managed-existing@example.com',
            'School_Name' => '관리 가능 기관',
            'Status' => '활성화',
            'ClassInOut' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openEditModal', $teacher->ID)
            ->set('newSkCode', 'SK-UNMANAGED')
            ->set('newSchoolName', '관리 불가 기관')
            ->call('save')
            ->assertForbidden();
    }

    public function test_full_access_admin_can_write_any_institution_contact(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedInstitutionWithCo('SK-ADMIN-WRITE', '관리자 기관', 'Any Manager');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openCreateModal')
            ->set('newName', '관리자 생성 교사')
            ->set('newEmail', 'admin-create@example.com')
            ->set('newSkCode', 'SK-ADMIN-WRITE')
            ->set('newSchoolName', '관리자 기관')
            ->call('save')
            ->assertHasNoErrors();

        $teacher = Teacher::query()->where('Email', 'admin-create@example.com')->first();
        $this->assertNotNull($teacher);
    }

    public function test_edit_teacher_allows_empty_email(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedInstitutionWithCo('SK-EDIT-EMAIL', '이메일 선택 기관', 'Admin Manager');

        $teacher = Teacher::query()->create([
            'SK_Code' => 'SK-EDIT-EMAIL',
            'Name' => '한민희 교수부장',
            'Email' => 'had-email@example.com',
            'School_Name' => '이메일 선택 기관',
            'Status' => '활성화',
            'ClassInOut' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacher->ID)
            ->set('newEmail', '')
            ->call('save')
            ->assertHasNoErrors();

        $teacher->refresh();
        $this->assertNull($teacher->Email);
    }

    public function test_create_teacher_allows_empty_email(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedInstitutionWithCo('SK-CREATE-EMAIL', '신규 이메일 기관', 'Admin Manager');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openCreateModal')
            ->set('newName', '이메일 없는 신규')
            ->set('newEmail', '')
            ->set('newSkCode', 'SK-CREATE-EMAIL')
            ->set('newSchoolName', '신규 이메일 기관')
            ->call('save')
            ->assertHasNoErrors();

        $teacher = Teacher::query()->where('Name', '이메일 없는 신규')->first();
        $this->assertNotNull($teacher);
        $this->assertNull($teacher->Email);
    }

    private function createContactTables(): void
    {
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
        });

        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 190)->nullable();
            $table->string('Email', 190)->nullable();
            $table->string('Phone', 190)->nullable();
            $table->string('Position', 100)->nullable();
            $table->string('School_Name', 255)->nullable();
            $table->text('Description')->nullable();
            $table->string('Status', 50)->nullable();
            $table->string('EmploymentType', 32)->default('unspecified');
            $table->boolean('ClassInOut')->nullable();
            $table->date('GrapeSEEDEssentials')->nullable();
            $table->date('LittleSEEDEssentials')->nullable();
            $table->dateTime('Created_Date')->nullable();
        });
    }

    private function seedTeacher(string $skCode, string $accountName, string $coName, string $teacherName): void
    {
        $this->seedInstitutionWithCo($skCode, $accountName, $coName);

        Teacher::query()->create([
            'SK_Code' => $skCode,
            'Name' => $teacherName,
            'Email' => strtolower($skCode).'@example.com',
            'School_Name' => $accountName,
            'Status' => '활성화',
            'ClassInOut' => true,
        ]);
    }

    private function seedInstitutionWithCo(string $skCode, string $accountName, string $coName): void
    {
        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => $accountName,
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => $skCode,
            'Account_Name' => $accountName,
            'CO' => $coName,
        ]);
    }
}
