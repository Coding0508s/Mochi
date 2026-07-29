<?php

namespace Tests\Feature;

use App\Enums\TeacherEmploymentType;
use App\Livewire\ContactList;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ContactListEmploymentTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createContactTables();
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

    public function test_create_modal_defaults_employment_type_to_unspecified(): void
    {
        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openCreateModal')
            ->assertSet('newEmploymentType', TeacherEmploymentType::Unspecified->value);
    }

    public function test_create_teacher_saves_employment_type_and_active_status(): void
    {
        $this->seedInstitution('SK-EMP-1');

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('newName', '풀타임 교사')
            ->set('newEmail', 'full-time@example.com')
            ->set('newSkCode', 'SK-EMP-1')
            ->set('newSchoolName', '테스트 기관')
            ->set('newEmploymentType', TeacherEmploymentType::FullTime->value)
            ->set('newClassParticipation', 'in')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'Email' => 'full-time@example.com',
            'Status' => '활성화',
            'EmploymentType' => 'full_time',
        ]);
    }

    public function test_create_teacher_defaults_employment_type_to_unspecified(): void
    {
        $this->seedInstitution('SK-EMP-2');

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('newName', '미지정 교사')
            ->set('newEmail', 'unspecified@example.com')
            ->set('newSkCode', 'SK-EMP-2')
            ->set('newSchoolName', '테스트 기관')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'Email' => 'unspecified@example.com',
            'Status' => '활성화',
            'EmploymentType' => 'unspecified',
        ]);
    }

    public function test_list_shows_employment_type_instead_of_account_status(): void
    {
        $this->seedInstitution('SK-EMP-3');

        Teacher::query()->create([
            'SK_Code' => 'SK-EMP-3',
            'Name' => '파트타임 교사',
            'Email' => 'part-time@example.com',
            'Status' => '비활성화',
            'EmploymentType' => TeacherEmploymentType::PartTime->value,
            'ClassInOut' => true,
        ]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->assertSee('파트타임 교사')
            ->assertSee('Part Time')
            ->assertSee('근무 형태')
            ->assertDontSee('>비활성화<', false)
            ->assertDontSeeHtml('>활성화</span>');
    }

    public function test_detail_modal_shows_employment_type_not_account_status(): void
    {
        $this->seedInstitution('SK-EMP-4');

        $teacherId = Teacher::query()->create([
            'SK_Code' => 'SK-EMP-4',
            'Name' => '상세 고용형태',
            'Email' => 'detail-emp@example.com',
            'Status' => '비활성화',
            'EmploymentType' => TeacherEmploymentType::FullTime->value,
            'ClassInOut' => false,
        ])->ID;

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openDetailModal', $teacherId)
            ->assertSet('selectedContact.employment_type', 'Full Time')
            ->assertSee('근무 형태')
            ->assertSee('Full Time')
            ->assertDontSeeHtml('>Status</th>')
            ->assertDontSee('비활성화');
    }

    public function test_edit_preserves_retired_status_while_updating_employment_type(): void
    {
        $this->seedInstitution('SK-EMP-5');

        $teacherId = Teacher::query()->create([
            'SK_Code' => 'SK-EMP-5',
            'Name' => '퇴직 고용형태',
            'Email' => 'retired-emp@example.com',
            'Status' => '퇴직',
            'EmploymentType' => TeacherEmploymentType::Unspecified->value,
            'ClassInOut' => false,
        ])->ID;

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->assertSet('newEmploymentType', 'unspecified')
            ->set('newEmploymentType', TeacherEmploymentType::PartTime->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '퇴직',
            'EmploymentType' => 'part_time',
        ]);
    }

    public function test_update_sets_inactive_teacher_status_to_active_without_changing_legacy_meaning_on_other_rows(): void
    {
        $this->seedInstitution('SK-EMP-6');

        $teacherId = Teacher::query()->create([
            'SK_Code' => 'SK-EMP-6',
            'Name' => '구비활성 교사',
            'Email' => 'legacy-inactive@example.com',
            'Status' => '비활성화',
            'EmploymentType' => TeacherEmploymentType::Unspecified->value,
            'ClassInOut' => false,
        ])->ID;

        $untouchedId = Teacher::query()->create([
            'SK_Code' => 'SK-EMP-6',
            'Name' => '건드리지않음',
            'Email' => 'untouched-inactive@example.com',
            'Status' => '비활성화',
            'EmploymentType' => TeacherEmploymentType::Unspecified->value,
            'ClassInOut' => false,
        ])->ID;

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->set('newEmploymentType', TeacherEmploymentType::FullTime->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '활성화',
            'EmploymentType' => 'full_time',
        ]);

        // 일괄 마이그레이션 없음: 수정하지 않은 행의 비활성화는 그대로 둔다.
        $this->assertDatabaseHas('Teachers', [
            'ID' => $untouchedId,
            'Status' => '비활성화',
        ]);
    }

    private function seedInstitution(string $skCode): void
    {
        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => '테스트 기관',
        ]);
    }
}
