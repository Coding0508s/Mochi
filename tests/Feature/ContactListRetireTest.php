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

    /**
     * 교사를 퇴직 상태로 만들고 S_RetirementList 행을 함께 생성합니다.
     *
     * 퇴직 처리는 더 이상 연락처 화면에서 수행하지 않으므로(교사 지원 현황 전용),
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

    public function test_contact_list_does_not_offer_retire_action(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '재직교사');

        Livewire::actingAs($admin)
            ->test(ContactList::class)
            ->call('openEditModal', $teacherId)
            ->assertDontSee('퇴직 처리')
            ->assertDontSeeHtml('wire:click="openRetireModal"');
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
