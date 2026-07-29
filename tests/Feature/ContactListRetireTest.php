<?php

namespace Tests\Feature;

use App\Livewire\ContactList;
use App\Models\Institution;
use App\Models\RetirementList;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ContactListRetireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createContactTables();
    }

    private function createContactTables(): void
    {
        Schema::dropIfExists('S_RetirementList');
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
            $table->string('TR', 100)->nullable();
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

        Schema::create('S_RetirementList', function (Blueprint $table): void {
            $table->bigIncrements('ID');
            $table->unsignedBigInteger('TearcherID')->nullable();
            $table->string('Name', 190)->nullable();
            $table->string('SK_Code', 190)->nullable();
            $table->string('Account_Name', 190)->nullable();
            $table->string('TR_Name', 190)->nullable();
            $table->dateTime('RetirementDate')->nullable();
            $table->boolean('RecommendYN')->nullable();
            $table->string('RecommendDescription', 190)->nullable();
            $table->string('Description', 190)->nullable();
            $table->string('Status', 190)->nullable();
            $table->string('FGC_Creator', 190)->nullable();
            $table->dateTime('FGC_CreateDate')->nullable();
            $table->string('FGC_LastModifier', 190)->nullable();
            $table->dateTime('FGC_LastModifyDate')->nullable();
        });
    }

    private function createInstitution(string $skCode, string $name, ?string $tr = null): void
    {
        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => $name,
        ]);

        \DB::table('S_Account_Information')->insert([
            'SK_Code' => $skCode,
            'Account_Name' => $name,
            'TR' => $tr,
        ]);
    }

    private function createTeacher(string $skCode, string $name, array $extra = []): int
    {
        return (int) Teacher::query()->create(array_merge([
            'SK_Code' => $skCode,
            'Name' => $name,
            'Email' => strtolower(str_replace(' ', '', $name)).'@example.com',
            'Status' => '활성화',
            'ClassInOut' => true,
        ], $extra))->ID;
    }

    /**
     * 복직/삭제 테스트는 이미 퇴직된 상태를 직접 준비합니다.
     */
    private function createRetiredTeacherWithRecord(string $skCode, string $name): int
    {
        $teacherId = $this->createTeacher($skCode, $name, [
            'Status' => '퇴직',
            'ClassInOut' => false,
        ]);

        RetirementList::query()->create([
            'TearcherID' => $teacherId,
            'Name' => $name,
            'SK_Code' => $skCode,
            'Account_Name' => '기관A',
            'TR_Name' => 'Coach A',
            'RetirementDate' => now(),
            'Status' => '퇴직',
            'RecommendYN' => false,
        ]);

        return $teacherId;
    }

    public function test_contact_list_offers_retire_action_for_active_teacher(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '재직교사');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->assertSee('퇴직 처리')
            ->assertSeeHtml('wire:click="openRetireModal"');
    }

    public function test_retire_from_contact_list_sets_status_and_retirement_record(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '한민희 교수부장', [
            'ClassInOut' => false,
            'Status' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'no')
            ->call('retire')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '퇴직',
            'ClassInOut' => false,
        ]);

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'Status' => '퇴직',
            'RecommendYN' => 0,
        ]);
    }

    public function test_retired_teacher_edit_modal_hides_retire_action(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createRetiredTeacherWithRecord('SK001', '이미퇴직');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->assertDontSeeHtml('wire:click="openRetireModal"')
            ->assertSee('복직 처리');
    }

    public function test_delete_removes_teacher_without_retirement_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '삭제대상');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('confirmDelete', $teacherId)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('Teachers', ['ID' => $teacherId]);
        $this->assertSame(0, RetirementList::query()->where('TearcherID', $teacherId)->count());
    }

    public function test_delete_after_retire_removes_teacher_and_retirement_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createRetiredTeacherWithRecord('SK001', '퇴직후삭제');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('confirmDelete', $teacherId)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('Teachers', ['ID' => $teacherId]);
        $this->assertDatabaseMissing('S_RetirementList', [
            'TearcherID' => $teacherId,
            'Name' => '퇴직후삭제',
        ]);
    }

    public function test_reinstate_from_contact_list_restores_teacher_and_marks_retirement_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createRetiredTeacherWithRecord('SK001', '복직대상');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->assertSee('복직 처리')
            ->call('openReinstateModal')
            ->set('reinstateClassParticipation', 'in')
            ->call('reinstate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '활성화',
            'ClassInOut' => true,
        ]);

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'Status' => '복직',
        ]);
    }

    public function test_reinstate_from_contact_list_with_new_institution(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherId = $this->createRetiredTeacherWithRecord('SK001', '기관변경복직');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openReinstateModal')
            ->set('reinstateClassParticipation', 'in')
            ->call('selectReinstateInstitution', 'SK002')
            ->assertSet('reinstateSkCode', 'SK002')
            ->call('reinstate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '활성화',
            'SK_Code' => 'SK002',
        ]);

        // 퇴직 당시 기관(SK001) 스냅샷은 이력 행에 보존
        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'SK_Code' => 'SK001',
            'Status' => '복직',
        ]);
    }

    public function test_contact_save_blocks_implicit_reinstate_for_retired_teacher(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '퇴직유지', [
            'Status' => '퇴직',
            'ClassInOut' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->set('newName', '퇴직유지(수정)')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Name' => '퇴직유지(수정)',
            'Status' => '퇴직',
        ]);
    }

    public function test_active_tab_excludes_retired_teachers_from_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '활성교사');
        $this->createRetiredTeacherWithRecord('SK001', '퇴직교사');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->assertSet('teacherStatusFilter', 'active')
            ->assertSee('활성교사')
            ->assertDontSee('퇴직교사')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_retired_tab_shows_only_retired_teachers(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '활성교사');
        $this->createRetiredTeacherWithRecord('SK001', '퇴직교사');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->set('teacherStatusFilter', 'retired')
            ->assertSee('퇴직교사')
            ->assertDontSee('활성교사')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_all_tab_shows_active_and_retired_teachers(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '활성교사');
        $this->createRetiredTeacherWithRecord('SK001', '퇴직교사');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->set('teacherStatusFilter', 'all')
            ->assertSee('활성교사')
            ->assertSee('퇴직교사')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 2);
    }

    public function test_all_tab_applies_class_participation_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '활성수업O', ['ClassInOut' => true]);
        $this->createTeacher('SK001', '활성수업X', ['ClassInOut' => false]);
        $this->createRetiredTeacherWithRecord('SK001', '퇴직수업O');
        Teacher::query()->where('Name', '퇴직수업O')->update(['ClassInOut' => true]);

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->set('teacherStatusFilter', 'all')
            ->set('employmentFilter', 'active')
            ->assertSee('활성수업O')
            ->assertSee('퇴직수업O')
            ->assertDontSee('활성수업X')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 2);
    }

    public function test_retired_tab_applies_class_participation_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createRetiredTeacherWithRecord('SK001', '퇴직수업O');
        Teacher::query()->where('Name', '퇴직수업O')->update(['ClassInOut' => true]);

        $this->createTeacher('SK001', '퇴직수업X', [
            'Status' => '퇴직',
            'ClassInOut' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->set('teacherStatusFilter', 'retired')
            ->set('employmentFilter', 'active')
            ->assertSee('퇴직수업O')
            ->assertDontSee('퇴직수업X')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_summary_counts_respect_teacher_status_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '활성참여', ['ClassInOut' => true]);
        $this->createTeacher('SK001', '활성미참여', ['ClassInOut' => false]);
        $this->createRetiredTeacherWithRecord('SK001', '퇴직교사');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->assertViewHas('totalCount', 2)
            ->assertViewHas('activeCount', 1)
            ->assertViewHas('inactiveCount', 1)
            ->set('teacherStatusFilter', 'retired')
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('activeCount', 0)
            ->assertViewHas('inactiveCount', 1)
            ->set('teacherStatusFilter', 'all')
            ->assertViewHas('totalCount', 3)
            ->assertViewHas('activeCount', 1)
            ->assertViewHas('inactiveCount', 2);
    }

    public function test_create_button_visible_on_all_teacher_status_tabs_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->assertSee('신규 생성')
            ->set('teacherStatusFilter', 'all')
            ->assertSee('신규 생성')
            ->set('teacherStatusFilter', 'retired')
            ->assertSee('신규 생성')
            ->set('teacherStatusFilter', 'active')
            ->assertSee('신규 생성');
    }
}
