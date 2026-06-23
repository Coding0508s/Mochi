<?php

namespace Tests\Feature;

use App\Actions\ReinstateTeacher;
use App\Actions\RetireTeacher;
use App\Livewire\CoachRetiredTeacherList;
use App\Models\RetirementList;
use App\Models\User;
use App\Support\TeacherRetirementRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        Schema::dropIfExists('S_TeacherMasterDB');
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
            $table->string('Phone', 50)->nullable();
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

        Schema::create('S_TeacherMasterDB', function ($table): void {
            $table->bigIncrements('ID');
            $table->unsignedBigInteger('TearcherID')->nullable();
            $table->unsignedBigInteger('TeacherID')->nullable();
            $table->string('Name', 190)->nullable();
            $table->string('SK_Code', 190)->nullable();
            $table->string('Account_Name', 190)->nullable();
            $table->string('School_Name', 190)->nullable();
            $table->string('TR_Name', 190)->nullable();
            $table->dateTime('RetirementDate')->nullable();
            $table->string('Status', 190)->nullable();
            $table->string('Email', 190)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->dateTime('GrapeSEEDEssentials')->nullable();
            $table->dateTime('LittleSEEDEssentials')->nullable();
            $table->string('Description', 190)->nullable();
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

    private function createMasterRecord(array $attributes): int
    {
        $skCode = (string) ($attributes['SK_Code'] ?? 'SK001');
        $name = (string) ($attributes['Name'] ?? '퇴직교사');
        $teacherId = $attributes['TearcherID'] ?? $attributes['TeacherID'] ?? null;

        if ($teacherId === null) {
            $teacherId = $this->createTeacher($skCode, $name, [
                'Status' => '퇴직',
                'School_Name' => $attributes['Account_Name'] ?? $attributes['School_Name'] ?? '기관A',
            ]);
            $attributes['TearcherID'] = $teacherId;
        } else {
            \DB::table('Teachers')->where('ID', $teacherId)->update([
                'Status' => '퇴직',
            ]);
        }

        unset($attributes['TeacherID']);

        \DB::table('S_TeacherMasterDB')->insert(array_merge([
            'TearcherID' => $teacherId,
            'Name' => $name,
            'SK_Code' => $skCode,
            'Account_Name' => $attributes['Account_Name'] ?? $attributes['School_Name'] ?? '기관A',
            'TR_Name' => 'Coach A',
            'RetirementDate' => now()->format('Y-m-d H:i:s'),
            'Status' => '퇴직',
        ], $attributes));

        return (int) $teacherId;
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
        $this->createMasterRecord(['Name' => 'A퇴직', 'SK_Code' => 'SK001', 'TR_Name' => 'Coach A', 'RetirementDate' => "{$year}-03-01 00:00:00"]);
        $this->createMasterRecord(['Name' => 'B퇴직', 'SK_Code' => 'SK002', 'TR_Name' => 'Coach B', 'RetirementDate' => "{$year}-04-01 00:00:00"]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->assertSee('A퇴직')
            ->assertSee('B퇴직');
    }

    public function test_retired_teacher_list_shows_teacher_phone(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createMasterRecord([
            'Name' => '전화있음',
            'SK_Code' => 'SK001',
            'TR_Name' => 'Coach A',
            'RetirementDate' => "{$year}-03-01 00:00:00",
            'Phone' => '010-9876-5432',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->assertSee('교사 전화번호')
            ->assertSee('010-9876-5432');
    }

    public function test_retired_teacher_list_prefers_teacher_phone_over_master_phone(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '우선전화', [
            'Status' => '퇴직',
            'Phone' => '010-1111-2222',
        ]);
        $this->createMasterRecord([
            'TearcherID' => $teacherId,
            'Name' => '우선전화',
            'SK_Code' => 'SK001',
            'TR_Name' => 'Coach A',
            'RetirementDate' => "{$year}-03-01 00:00:00",
            'Phone' => '010-9999-8888',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->assertSee('010-1111-2222')
            ->assertDontSee('010-9999-8888');
    }

    public function test_exports_retired_teacher_list_to_excel(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;
        $now = now();
        Carbon::setTestNow($now);

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createMasterRecord([
            'Name' => '엑셀대상',
            'SK_Code' => 'SK001',
            'TR_Name' => 'Coach A',
            'RetirementDate' => "{$year}-03-01 00:00:00",
            'Phone' => '010-5555-6666',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->assertSee('엑셀 다운로드')
            ->call('exportToExcel')
            ->assertFileDownloaded('퇴직교사_리스트_'.$now->format('Ymd_His').'.xlsx');
    }

    public function test_export_shows_error_when_no_rows_match_filters(): void
    {
        $admin = $this->createAdminUser();

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('search', '존재하지않는교사')
            ->call('exportToExcel')
            ->assertNoFileDownloaded()
            ->assertSee('다운로드할 데이터가 없습니다.');
    }

    public function test_coach_sees_only_own_tr_retirements(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createMasterRecord(['Name' => '내TR퇴직', 'SK_Code' => 'SK001', 'TR_Name' => 'Coach A', 'RetirementDate' => "{$year}-03-01 00:00:00"]);
        $this->createMasterRecord(['Name' => '다른TR퇴직', 'SK_Code' => 'SK002', 'TR_Name' => 'Coach B', 'RetirementDate' => "{$year}-03-01 00:00:00"]);

        Livewire::actingAs($coach)
            ->test(CoachRetiredTeacherList::class)
            ->assertSee('내TR퇴직')
            ->assertDontSee('다른TR퇴직');
    }

    public function test_search_filters_by_name(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createMasterRecord(['Name' => '김퇴직', 'RetirementDate' => "{$year}-03-01 00:00:00"]);
        $this->createMasterRecord(['Name' => '박퇴직', 'RetirementDate' => "{$year}-03-01 00:00:00"]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('search', '김퇴직')
            ->assertSee('김퇴직')
            ->assertDontSee('박퇴직');
    }

    public function test_master_list_shows_account_name_position_and_recommendation(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK2528', '양산 니트', 'Levi Kim');
        $teacherId = $this->createTeacher('SK2528', '박세빌 Lily', [
            'Status' => '퇴직',
            'Position' => '교사',
            'School_Name' => '양산 니트',
        ]);

        $this->createMasterRecord([
            'TearcherID' => $teacherId,
            'Name' => '박세빌 Lily',
            'SK_Code' => 'SK2528',
            'Account_Name' => '양산 니트',
            'TR_Name' => 'Levi Kim',
            'RetirementDate' => "{$year}-02-01 00:00:00",
        ]);

        $this->createRetirementRecord([
            'TearcherID' => $teacherId,
            'Name' => '박세빌 Lily',
            'SK_Code' => 'SK2528',
            'Account_Name' => '양산 니트',
            'TR_Name' => 'Levi Kim',
            'RetirementDate' => "{$year}-02-01 00:00:00",
            'RecommendYN' => true,
            'RecommendDescription' => '높은 GrapeSEED 이해도',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', $year)
            ->set('search', 'SK2528')
            ->assertSee('양산 니트')
            ->assertSee('교사')
            ->assertSee('Y');
    }

    public function test_year_filter_limits_results(): void
    {
        $admin = $this->createAdminUser();

        $this->createMasterRecord(['Name' => '올해퇴직', 'RetirementDate' => now()->format('Y-m-d H:i:s')]);
        $this->createMasterRecord(['Name' => '작년퇴직', 'RetirementDate' => now()->subYear()->format('Y-m-d H:i:s')]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', (string) now()->year)
            ->assertSee('올해퇴직')
            ->assertDontSee('작년퇴직');
    }

    public function test_list_includes_retired_teacher_without_master_row(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $teacherId = $this->createTeacher('SK2575', '마스터없음퇴직', [
            'Status' => '퇴직',
            'School_Name' => '양산 니트',
        ]);

        $this->createRetirementRecord([
            'TearcherID' => $teacherId,
            'Name' => '마스터없음퇴직',
            'SK_Code' => 'SK2575',
            'Account_Name' => '양산 니트',
            'RetirementDate' => "{$year}-05-01 00:00:00",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', (string) $year)
            ->assertSee('마스터없음퇴직')
            ->assertSee("{$year}-05-01");
    }

    public function test_list_is_ordered_by_retirement_date_desc(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach A');
        $this->createInstitution('SK003', '기관C', 'Coach A');

        $this->createMasterRecord(['Name' => '먼저퇴직', 'SK_Code' => 'SK001', 'RetirementDate' => '2026-01-10 00:00:00']);
        $this->createMasterRecord(['Name' => '나중퇴직', 'SK_Code' => 'SK002', 'RetirementDate' => '2026-05-20 00:00:00']);
        $this->createMasterRecord(['Name' => '중간퇴직', 'SK_Code' => 'SK003', 'RetirementDate' => '2026-03-15 00:00:00']);

        $retirements = Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->viewData('retirements');

        $names = collect($retirements->items())
            ->map(fn ($row): string => (string) $row->Name)
            ->all();

        $this->assertSame(['나중퇴직', '중간퇴직', '먼저퇴직'], $names);
    }

    public function test_year_filter_all_shows_every_retired_year(): void
    {
        $admin = $this->createAdminUser();

        $this->createMasterRecord(['Name' => '올해퇴직', 'RetirementDate' => now()->format('Y-m-d H:i:s')]);
        $this->createMasterRecord(['Name' => '작년퇴직', 'RetirementDate' => now()->subYear()->format('Y-m-d H:i:s')]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', '')
            ->assertSee('전체')
            ->assertSee('올해퇴직')
            ->assertSee('작년퇴직');
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
        $this->assertDatabaseHas('S_TeacherMasterDB', [
            'TearcherID' => $teacherId,
            'Name' => '퇴직대상',
            'SK_Code' => 'SK001',
            'Status' => '퇴직',
        ]);

        $record = RetirementList::query()->where('TearcherID', $teacherId)->first();
        $this->assertNotNull($record);
        $this->assertSame('기관A', $record->Account_Name);
    }

    public function test_retire_teacher_stores_recommendation_when_provided(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '추천대상');

        $recommendation = TeacherRetirementRecommendation::fromForm(
            'yes',
            '높은 GrapeSEED 이해도',
        );

        app(RetireTeacher::class)->execute($teacherId, $admin, $recommendation);

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'RecommendYN' => 1,
            'RecommendDescription' => '높은 GrapeSEED 이해도',
        ]);
    }

    public function test_retire_teacher_stores_default_description_when_not_recommended(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '비추천대상');

        $recommendation = TeacherRetirementRecommendation::fromForm('no', null);

        app(RetireTeacher::class)->execute($teacherId, $admin, $recommendation);

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'RecommendYN' => 0,
            'RecommendDescription' => '해당사항없음',
        ]);
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

        $otherTrRecordId = $this->createMasterRecord([
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

        $this->createMasterRecord([
            'Name' => '숨김기관퇴직',
            'SK_Code' => 'SK-HIDDEN',
            'RetirementDate' => "{$year}-03-01 00:00:00",
        ]);
        $this->createMasterRecord([
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

        $id = $this->createMasterRecord([
            'Name' => '상세교사',
            'RetirementDate' => "{$year}-05-01 00:00:00",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->call('openDetailModal', $id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedRetirement.name', '상세교사');
    }

    public function test_reinstated_teacher_remains_in_list_with_reinstated_badge(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '복직이력유지', ['Status' => '활성화']);

        app(RetireTeacher::class)->execute($teacherId, $admin);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', $year)
            ->assertSee('복직이력유지');

        app(ReinstateTeacher::class)->execute($teacherId, $admin, true);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', $year)
            ->assertSee('복직이력유지')
            ->assertSee('복직');

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'Status' => '복직',
        ]);
        $this->assertDatabaseHas('S_TeacherMasterDB', [
            'TearcherID' => $teacherId,
            'Status' => '활성화',
        ]);
    }

    public function test_reinstate_with_new_institution_moves_teacher_and_keeps_snapshot(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherId = $this->createTeacher('SK001', '기관이동복직', ['School_Name' => '기관A']);

        app(RetireTeacher::class)->execute($teacherId, $admin);
        app(ReinstateTeacher::class)->execute($teacherId, $admin, true, 'SK002');

        // 교사는 새 기관으로 이동
        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '활성화',
            'SK_Code' => 'SK002',
            'School_Name' => '기관B',
        ]);

        // 퇴직 이력 행에는 전 근무 기관(SK001/기관A) 스냅샷 보존
        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'Status' => '복직',
        ]);

        // 마스터는 현재(복직) 기관으로 동기화
        $this->assertDatabaseHas('S_TeacherMasterDB', [
            'TearcherID' => $teacherId,
            'SK_Code' => 'SK002',
            'Status' => '활성화',
        ]);
    }

    public function test_reinstate_with_unknown_institution_is_rejected(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '잘못된기관복직');

        app(RetireTeacher::class)->execute($teacherId, $admin);

        $this->expectException(\InvalidArgumentException::class);

        app(ReinstateTeacher::class)->execute($teacherId, $admin, true, 'SK-NOPE');
    }

    public function test_re_retirement_creates_new_history_row(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherId = $this->createTeacher('SK001', '재퇴직이력', ['School_Name' => '기관A']);

        app(RetireTeacher::class)->execute($teacherId, $admin);
        app(ReinstateTeacher::class)->execute($teacherId, $admin, true, 'SK002');
        app(RetireTeacher::class)->execute($teacherId, $admin);

        $this->assertSame(2, RetirementList::query()->where('TearcherID', $teacherId)->count());

        // 1차 퇴직 이력: 기관A에서 퇴직 → 복직
        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'SK_Code' => 'SK001',
            'Status' => '복직',
        ]);

        // 2차 퇴직 이력: 기관B에서 퇴직
        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'SK_Code' => 'SK002',
            'Status' => '퇴직',
        ]);
    }

    public function test_reinstate_with_selected_institution_from_detail_modal(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherId = $this->createTeacher('SK001', '모달기관선택복직');

        app(RetireTeacher::class)->execute($teacherId, $admin);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', (string) $year)
            ->call('openDetailModal', $teacherId)
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

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $teacherId,
            'SK_Code' => 'SK001',
            'Status' => '복직',
        ]);
    }

    public function test_reinstate_from_retired_list_detail_modal(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '리스트복직');

        app(RetireTeacher::class)->execute($teacherId, $admin);

        Livewire::actingAs($admin)
            ->test(CoachRetiredTeacherList::class)
            ->set('filterYear', (string) $year)
            ->call('openDetailModal', $teacherId)
            ->call('openReinstateModal')
            ->set('reinstateClassParticipation', 'out')
            ->call('reinstate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $teacherId,
            'Status' => '활성화',
            'ClassInOut' => false,
        ]);
    }
}
