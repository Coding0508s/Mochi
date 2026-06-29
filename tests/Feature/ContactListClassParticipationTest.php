<?php

namespace Tests\Feature;

use App\Livewire\ContactList;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ContactListClassParticipationTest extends TestCase
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
            $table->boolean('ClassInOut')->nullable();
            $table->date('GrapeSEEDEssentials')->nullable();
            $table->date('LittleSEEDEssentials')->nullable();
            $table->dateTime('Created_Date')->nullable();
        });
    }

    public function test_create_teacher_with_unspecified_class_participation_saves_null(): void
    {
        $this->seedInstitution('SK-UNSET-1');

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('newName', '미지정 교사')
            ->set('newEmail', 'unset@example.com')
            ->set('newSkCode', 'SK-UNSET-1')
            ->set('newSchoolName', '테스트 기관')
            ->set('newClassParticipation', '')
            ->call('save')
            ->assertHasNoErrors();

        $teacher = Teacher::query()->where('Email', 'unset@example.com')->first();
        $this->assertNotNull($teacher);
        $this->assertNull($teacher->getAttributes()['ClassInOut']);
    }

    public function test_edit_modal_loads_unspecified_when_class_in_out_is_null(): void
    {
        $this->seedInstitution('SK-UNSET-2');

        $teacher = Teacher::query()->create([
            'SK_Code' => 'SK-UNSET-2',
            'Name' => '기존 미지정',
            'Email' => 'existing-unset@example.com',
            'ClassInOut' => null,
            'Status' => '활성화',
        ]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openEditModal', $teacher->ID)
            ->assertSet('newClassParticipation', '');
    }

    public function test_update_teacher_to_unspecified_clears_class_in_out(): void
    {
        $this->seedInstitution('SK-UNSET-3');

        $teacher = Teacher::query()->create([
            'SK_Code' => 'SK-UNSET-3',
            'Name' => '수업참여 교사',
            'Email' => 'was-in@example.com',
            'ClassInOut' => true,
            'Status' => '활성화',
        ]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openEditModal', $teacher->ID)
            ->set('newClassParticipation', '')
            ->call('save')
            ->assertHasNoErrors();

        $teacher->refresh();
        $this->assertNull($teacher->getAttributes()['ClassInOut']);
    }

    public function test_employment_filter_excludes_unspecified_from_active_and_inactive(): void
    {
        $this->seedInstitution('SK-FILTER-1');

        Teacher::query()->create([
            'SK_Code' => 'SK-FILTER-1',
            'Name' => '수업O',
            'Email' => 'in@example.com',
            'ClassInOut' => true,
        ]);

        Teacher::query()->create([
            'SK_Code' => 'SK-FILTER-1',
            'Name' => '수업X',
            'Email' => 'out@example.com',
            'ClassInOut' => false,
        ]);

        Teacher::query()->create([
            'SK_Code' => 'SK-FILTER-1',
            'Name' => '미지정',
            'Email' => 'unset-filter@example.com',
            'ClassInOut' => null,
        ]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('employmentFilter', 'active')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 1)
            ->set('employmentFilter', 'inactive')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 1)
            ->set('employmentFilter', 'all')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 3);
    }

    private function seedInstitution(string $skCode): void
    {
        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => '테스트 기관',
        ]);
    }
}
