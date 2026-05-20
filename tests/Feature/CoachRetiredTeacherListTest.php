<?php

namespace Tests\Feature;

use App\Actions\RetireTeacher;
use App\Livewire\CoachRetiredTeacherList;
use App\Models\RetirementList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CoachRetiredTeacherListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    private function createRequiredTables(): void
    {
        Schema::dropIfExists('S_RetirementList');
        Schema::dropIfExists('institution_visibility_overrides');
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');
        Schema::dropIfExists('employee');

        Schema::create('S_AccountName', function ($table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
        });

        Schema::create('Teachers', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('School_Name', 255)->nullable();
            $table->string('Name', 255)->nullable();
            $table->string('Position', 100)->nullable();
            $table->string('Status', 50)->nullable();
            $table->boolean('ClassInOut')->default(true);
            $table->text('Description')->nullable();
        });

        Schema::create('employee', function ($table): void {
            $table->string('EMPNO')->primary();
            $table->string('WORKDEPT')->nullable();
            $table->string('KOREANAME')->nullable();
            $table->string('ENGLISHNAME')->nullable();
            $table->string('EMAIL')->nullable();
        });

        Schema::create('institution_visibility_overrides', function ($table): void {
            $table->increments('id');
            $table->string('sk_code', 100);
            $table->timestamp('hidden_at')->nullable();
        });

        Schema::create('S_RetirementList', function ($table): void {
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

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'team' => 'CO',
        ]);
    }

    private function createCoachUser(string $name = 'Coach A', string $email = 'coacha@example.com'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'team' => 'TR',
            'is_admin' => false,
        ]);
    }

    private function createInstitution(string $skCode, string $name, ?string $tr = null): void
    {
        \DB::table('S_AccountName')->insert([
            'SKcode' => $skCode,
            'AccountName' => $name,
        ]);

        if ($tr) {
            \DB::table('S_Account_Information')->insert([
                'SK_Code' => $skCode,
                'Account_Name' => $name,
                'TR' => $tr,
            ]);
        }
    }

    private function createTeacher(string $skCode, string $name, array $extra = []): int
    {
        return (int) \DB::table('Teachers')->insertGetId(array_merge([
            'SK_Code' => $skCode,
            'Name' => $name,
            'ClassInOut' => true,
        ], $extra));
    }

    private function createRetirementRecord(array $attributes): int
    {
        return (int) \DB::table('S_RetirementList')->insertGetId(array_merge([
            'Name' => '퇴직교사',
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'TR_Name' => 'Coach A',
            'RetirementDate' => now()->format('Y-m-d H:i:s'),
            'Status' => '퇴직',
            'RecommendYN' => false,
        ], $attributes));
    }

    public function test_page_requires_authentication(): void
    {
        $this->get(route('coach.retired-teachers.index'))
            ->assertRedirect();
    }

    public function test_admin_sees_all_retired_teachers_in_scope(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createRetirementRecord(['Name' => 'A퇴직', 'SK_Code' => 'SK001', 'TR_Name' => 'Coach A', 'RetirementDate' => "{$year}-03-01 00:00:00"]);
        $this->createRetirementRecord(['Name' => 'B퇴직', 'SK_Code' => 'SK002', 'TR_Name' => 'Coach B', 'RetirementDate' => "{$year}-04-01 00:00:00"]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->assertSee('A퇴직')
            ->assertSee('B퇴직');
    }

    public function test_coach_sees_only_own_tr_retirements(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createRetirementRecord(['Name' => '내TR퇴직', 'SK_Code' => 'SK001', 'TR_Name' => 'Coach A', 'RetirementDate' => "{$year}-03-01 00:00:00"]);
        $this->createRetirementRecord(['Name' => '다른TR퇴직', 'SK_Code' => 'SK002', 'TR_Name' => 'Coach B', 'RetirementDate' => "{$year}-03-01 00:00:00"]);

        Livewire::actingAs($coach)
            ->test(CoachRetiredTeacherList::class)
            ->assertSee('내TR퇴직')
            ->assertDontSee('다른TR퇴직');
    }

    public function test_search_filters_by_name(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createRetirementRecord(['Name' => '김퇴직', 'RetirementDate' => "{$year}-03-01 00:00:00"]);
        $this->createRetirementRecord(['Name' => '박퇴직', 'RetirementDate' => "{$year}-03-01 00:00:00"]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('search', '김퇴직')
            ->assertSee('김퇴직')
            ->assertDontSee('박퇴직');
    }

    public function test_year_filter_limits_results(): void
    {
        $admin = $this->createAdminUser();

        $this->createRetirementRecord(['Name' => '올해퇴직', 'RetirementDate' => now()->format('Y-m-d H:i:s')]);
        $this->createRetirementRecord(['Name' => '작년퇴직', 'RetirementDate' => now()->subYear()->format('Y-m-d H:i:s')]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', now()->year)
            ->assertSee('올해퇴직')
            ->assertDontSee('작년퇴직');
    }

    public function test_retire_teacher_writes_retirement_list_row(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '퇴직대상', ['Position' => '교사']);

        app(RetireTeacher::class)->execute($teacherId, $admin);

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
        ]);

        $record = RetirementList::query()->where('TearcherID', $teacherId)->first();
        $this->assertNotNull($record);
        $this->assertSame('기관A', $record->Account_Name);
    }

    public function test_retire_teacher_updates_existing_retirement_list_row(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '퇴직대상');
        $this->createRetirementRecord([
            'TearcherID' => $teacherId,
            'Name' => '이전이름',
            'RetirementDate' => '2020-01-01 00:00:00',
            'RecommendYN' => true,
            'RecommendDescription' => '레거시 추천',
            'FGC_Creator' => 'legacy@example.com',
            'FGC_CreateDate' => '2019-06-01 00:00:00',
        ]);

        app(RetireTeacher::class)->execute($teacherId, $admin);

        $this->assertSame(1, RetirementList::query()->where('TearcherID', $teacherId)->count());
        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'Name' => '퇴직대상',
            'RetirementDate' => '2020-01-01 00:00:00',
            'RecommendYN' => 1,
            'RecommendDescription' => '레거시 추천',
            'FGC_Creator' => 'legacy@example.com',
            'FGC_CreateDate' => '2019-06-01 00:00:00',
        ]);
    }

    public function test_coach_cannot_open_detail_modal_for_other_tr_record(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $otherTrRecordId = $this->createRetirementRecord([
            'Name' => '다른TR상세',
            'SK_Code' => 'SK002',
            'TR_Name' => 'Coach B',
            'RetirementDate' => "{$year}-03-01 00:00:00",
        ]);

        Livewire::actingAs($coach)
            ->test(CoachRetiredTeacherList::class)
            ->call('openDetailModal', $otherTrRecordId)
            ->assertSet('showDetailModal', false)
            ->assertSet('selectedRetirement', null);
    }

    public function test_hidden_institution_retirements_are_excluded(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createRetirementRecord([
            'Name' => '숨김기관퇴직',
            'SK_Code' => 'SK-HIDDEN',
            'RetirementDate' => "{$year}-03-01 00:00:00",
        ]);
        $this->createRetirementRecord([
            'Name' => '일반기관퇴직',
            'SK_Code' => 'SK-VISIBLE',
            'RetirementDate' => "{$year}-03-01 00:00:00",
        ]);

        \DB::table('institution_visibility_overrides')->insert([
            'sk_code' => 'SK-HIDDEN',
            'hidden_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->assertDontSee('숨김기관퇴직')
            ->assertSee('일반기관퇴직');
    }

    public function test_detail_modal_opens_for_scoped_record(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $id = $this->createRetirementRecord([
            'Name' => '상세교사',
            'RetirementDate' => "{$year}-05-01 00:00:00",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->call('openDetailModal', $id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedRetirement.name', '상세교사');
    }
}
