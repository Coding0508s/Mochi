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

    public function test_create_teacher_saves_long_description(): void
    {
        $this->seedInstitution('SK-DESC-1');

        $user = User::factory()->admin()->create();
        $longDescription = str_repeat('가나다라마바사아자차', 40); // 400자

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('newName', '긴 비고 교사')
            ->set('newEmail', 'long-desc@example.com')
            ->set('newSkCode', 'SK-DESC-1')
            ->set('newSchoolName', '테스트 기관')
            ->set('newDescription', $longDescription)
            ->call('save')
            ->assertHasNoErrors();

        $teacher = Teacher::query()->where('Email', 'long-desc@example.com')->first();
        $this->assertNotNull($teacher);
        $this->assertSame($longDescription, $teacher->Description);
    }

    public function test_create_teacher_rejects_description_over_limit(): void
    {
        $this->seedInstitution('SK-DESC-2');

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('newName', '초과 비고 교사')
            ->set('newEmail', 'over-desc@example.com')
            ->set('newSkCode', 'SK-DESC-2')
            ->set('newSchoolName', '테스트 기관')
            ->set('newDescription', str_repeat('가', 5001))
            ->call('save')
            ->assertHasErrors(['newDescription']);

        $this->assertNull(Teacher::query()->where('Email', 'over-desc@example.com')->first());
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

    public function test_employment_filter_counts_unspecified_as_not_participating(): void
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
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 2)
            ->set('employmentFilter', 'all')
            ->assertViewHas('teachers', fn ($paginator) => $paginator->total() === 3);
    }

    public function test_contact_list_shows_unset_account_status_as_active(): void
    {
        $this->seedInstitution('SK-STATUS-1');

        Teacher::query()->create([
            'SK_Code' => 'SK-STATUS-1',
            'Name' => '상태 미지정 교사',
            'Email' => 'unset-status@example.com',
            'ClassInOut' => true,
            'Status' => null,
        ]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->assertSee('상태 미지정 교사')
            ->assertSee('활성화');
    }

    public function test_detail_modal_shows_class_participation_and_account_status_separately(): void
    {
        $this->seedInstitution('SK-DETAIL-1');

        $teacherId = Teacher::query()->create([
            'SK_Code' => 'SK-DETAIL-1',
            'Name' => '한민희 교수부장',
            'Email' => 'detail-status@example.com',
            'School_Name' => '경기 시흥 예일유치원',
            'Status' => null,
            'ClassInOut' => false,
        ])->ID;

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openDetailModal', $teacherId)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedContact.class_participation', '수업 미참여')
            ->assertSet('selectedContact.account_status', '활성화')
            ->assertSee('수업참여')
            ->assertSee('Status')
            ->assertSee('수업 미참여')
            ->assertSee('활성화')
            ->assertDontSee('>퇴직<', false);
    }

    public function test_detail_modal_keeps_long_description_in_full_width_table_cell(): void
    {
        $this->seedInstitution('SK-DETAIL-LONG');

        $longDescription = 'This teacher took a while finishing the GS Essentials. '
            .'She taught at FSS for a year (2025) however, not GS nor LS. '
            .'As a young, fluent native speaker, Kate교수부장님 would like to continue '
            .'to raise her up as one of the teachers.';

        $teacherId = Teacher::query()->create([
            'SK_Code' => 'SK-DETAIL-LONG',
            'Name' => '허 Jina',
            'Email' => 'long-detail@example.com',
            'School_Name' => '용인 구갈 성민어학원',
            'Description' => $longDescription,
            'Status' => '활성화',
            'ClassInOut' => true,
        ])->ID;

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openDetailModal', $teacherId)
            ->assertSet('showDetailModal', true)
            ->assertSee($longDescription)
            // display:block인 mochi-multiline-readout을 td에 쓰면 colspan이 깨짐
            ->assertDontSeeHtml('mochi-multiline-readout')
            ->assertSeeHtml('colspan="3" class="px-3 py-2 font-medium text-gray-900 text-left whitespace-pre-wrap break-words"')
            ->assertSeeHtml('mochi-modal-body-scroll');
    }

    public function test_contact_list_shows_active_status_when_class_participation_is_out(): void
    {
        $this->seedInstitution('SK-STATUS-2');

        Teacher::query()->create([
            'SK_Code' => 'SK-STATUS-2',
            'Name' => '수업미참여 활성 교사',
            'Email' => 'out-active@example.com',
            'ClassInOut' => false,
            'Status' => null,
        ]);

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->set('employmentFilter', 'inactive')
            ->assertSee('수업미참여 활성 교사')
            ->assertSee('활성화')
            ->assertDontSee('>퇴직<', false);
    }

    public function test_detail_modal_shows_unspecified_class_participation_as_not_participating(): void
    {
        $this->seedInstitution('SK-DETAIL-2');

        $teacherId = Teacher::query()->create([
            'SK_Code' => 'SK-DETAIL-2',
            'Name' => '미참여 표시 교사',
            'Email' => 'unset-detail@example.com',
            'ClassInOut' => null,
            'Status' => '활성화',
        ])->ID;

        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('openDetailModal', $teacherId)
            ->assertSet('selectedContact.class_participation', '미참여')
            ->assertSee('미참여')
            ->assertDontSee('>미지정<', false);
    }

    private function seedInstitution(string $skCode): void
    {
        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => '테스트 기관',
        ]);
    }
}
