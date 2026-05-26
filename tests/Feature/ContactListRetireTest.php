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

    public function test_contact_list_retire_writes_retirement_list_and_teacher_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '퇴직대상');

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
            'Name' => '퇴직대상',
            'SK_Code' => 'SK001',
            'Status' => '퇴직',
            'RecommendYN' => 0,
            'RecommendDescription' => '해당사항없음',
        ]);

        $this->assertSame(1, RetirementList::query()->where('TearcherID', $teacherId)->count());
    }

    public function test_contact_list_retire_requires_recommend_description_when_yes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '추천필수');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'yes')
            ->set('retireRecommendDescription', '')
            ->call('retire')
            ->assertHasErrors(['retireRecommendDescription']);
    }

    public function test_contact_list_retire_stores_recommendation_when_yes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '추천교사');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'yes')
            ->set('retireRecommendDescription', '높은 GrapeSEED 이해도')
            ->call('retire')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'RecommendYN' => 1,
            'RecommendDescription' => '높은 GrapeSEED 이해도',
        ]);
    }

    public function test_contact_list_retire_does_not_set_inactive_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherId = $this->createTeacher('SK002', '상태검증');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'no')
            ->call('retire');

        $teacher = Teacher::find($teacherId);
        $this->assertSame('퇴직', $teacher->Status);
        $this->assertNotSame('비활성화', $teacher->Status);
    }

    public function test_coach_without_tr_scope_cannot_retire_from_contact_list(): void
    {
        $coach = User::factory()->create([
            'name' => 'Coach A',
            'email' => 'coacha@example.com',
            'team' => 'TR',
            'is_admin' => false,
        ]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherId = $this->createTeacher('SK002', '다른TR교사');

        Livewire::actingAs($coach)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'no')
            ->call('retire')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '활성화',
        ]);

        $this->assertSame(0, RetirementList::query()->where('TearcherID', $teacherId)->count());
    }

    public function test_coach_can_retire_own_tr_teacher_from_contact_list(): void
    {
        $coach = User::factory()->create([
            'name' => 'Coach A',
            'email' => 'coacha@example.com',
            'team' => 'TR',
            'is_admin' => false,
        ]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '내TR교사');

        Livewire::actingAs($coach)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->assertSee('퇴직 처리')
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'no')
            ->call('retire');

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '퇴직',
        ]);
    }

    public function test_retire_button_hidden_for_already_retired_teacher(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '이미퇴직', [
            'Status' => '퇴직',
            'ClassInOut' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->assertDontSee('퇴직 처리');
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
        $teacherId = $this->createTeacher('SK001', '퇴직후삭제');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'no')
            ->call('retire')
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
        $teacherId = $this->createTeacher('SK001', '복직대상');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->call('openRetireModal')
            ->set('retireRecommendChoice', 'no')
            ->call('retire')
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
            ->set('newEmploymentStatus', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '퇴직',
        ]);
    }
}
