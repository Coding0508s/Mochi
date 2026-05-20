<?php

namespace Tests\Feature;

use App\Actions\UpdateTeacherSupport;
use App\Livewire\CoachTeacherSupportList;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CoachTeacherSupportListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    private function createRequiredTables(): void
    {
        Schema::dropIfExists('S_SolutionConsulting');
        Schema::dropIfExists('S_Support_U31');
        Schema::dropIfExists('S_Support_U21');
        Schema::dropIfExists('S_SupportLittleSEED_ONLVA');
        Schema::dropIfExists('S_Support_OpenClass');
        Schema::dropIfExists('S_Support_OnSite');
        Schema::dropIfExists('S_Support_LVA');
        Schema::dropIfExists('S_Support_NewTeacher');
        Schema::dropIfExists('teacher_unit31_plus_support_reports');
        Schema::dropIfExists('teacher_unit21_plus_support_reports');
        Schema::dropIfExists('teacher_open_class_support_reports');
        Schema::dropIfExists('teacher_pro_con_support_reports');
        Schema::dropIfExists('teacher_onsite_support_reports');
        Schema::dropIfExists('teacher_littleseed_con_support_reports');
        Schema::dropIfExists('teacher_ls_onsite_lva_support_reports');
        Schema::dropIfExists('teacher_lva_fb_support_reports');
        Schema::dropIfExists('teacher_lva_fr_support_reports');
        Schema::dropIfExists('teacher_demo_lesson_support_reports');
        Schema::dropIfExists('S_SupportInfo_Account');
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
            $table->string('Possibility', 20)->nullable();
        });

        Schema::create('S_Account_Information', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
        });

        Schema::create('Teachers', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('School_Name', 255)->nullable();
            $table->string('Name', 255)->nullable();
            $table->string('Position', 100)->nullable();
            $table->string('Email', 255)->nullable();
            $table->string('Phone', 100)->nullable();
            $table->text('Description')->nullable();
            $table->string('Status', 50)->nullable();
            $table->boolean('ClassInOut')->default(true);
            $table->date('Plan_1st_Support_Date')->nullable();
            $table->date('Plan_2nd_Support_Date')->nullable();
            $table->string('Plan_1st_Support_Type', 100)->nullable();
            $table->string('Plan_2nd_Support_Type', 100)->nullable();
            $table->date('_1st_Support_Date')->nullable();
            $table->date('_2nd_Support_Date')->nullable();
            $table->date('_3rd_Support_Date')->nullable();
            $table->date('_4th_Support_Date')->nullable();
            $table->string('_1st_Support_Type', 100)->nullable();
            $table->string('_2nd_Support_Type', 100)->nullable();
            $table->string('_3rd_Support_Type', 100)->nullable();
            $table->string('_4th_Support_Type', 100)->nullable();
            $table->date('GrapeSEEDEssentials')->nullable();
            $table->date('LittleSEEDEssentials')->nullable();
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

        Schema::create('S_Support_NewTeacher', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
            $table->unsignedTinyInteger('ReportType')->nullable();
        });

        Schema::create('S_Support_LVA', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
            $table->unsignedTinyInteger('ReportType')->nullable();
            $table->string('LVA_TYPE', 10)->nullable();
        });

        Schema::create('S_Support_OnSite', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_Support_OpenClass', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_SupportLittleSEED_ONLVA', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_Support_U21', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_Support_U31', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_SolutionConsulting', function ($table): void {
            $table->increments('ID');
            $table->string('TR_Name', 255)->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('S_SupportInfo_Account', function ($table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->dateTime('Support_Date')->nullable();
            $table->string('Meet_Time', 20)->nullable();
            $table->string('Target', 255)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->text('Issue')->nullable();
            $table->text('Others')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CreatedDate')->nullable();
            $table->timestamp('CompletedDate')->nullable();
        });

        Schema::create('teacher_unit31_plus_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->unsignedTinyInteger('progress_unit')->nullable();
            $table->unsignedTinyInteger('progress_lesson')->nullable();
            $table->string('progress_other', 255)->nullable();
            $table->json('procedures')->nullable();
            $table->json('verbal_materials')->nullable();
            $table->json('language_arts_materials')->nullable();
            $table->text('verbal_comments')->nullable();
            $table->text('language_arts_comments')->nullable();
            $table->text('overall_comments')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_unit21_plus_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->unsignedTinyInteger('progress_unit')->nullable();
            $table->unsignedTinyInteger('progress_lesson')->nullable();
            $table->string('progress_other', 255)->nullable();
            $table->json('procedures')->nullable();
            $table->json('verbal_materials')->nullable();
            $table->json('language_arts_materials')->nullable();
            $table->text('verbal_comments')->nullable();
            $table->text('language_arts_comments')->nullable();
            $table->text('overall_comments')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_open_class_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->unsignedTinyInteger('progress_unit')->nullable();
            $table->unsignedTinyInteger('progress_lesson')->nullable();
            $table->string('progress_other', 255)->nullable();
            $table->json('procedures')->nullable();
            $table->json('support_content')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_pro_con_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->json('procedures')->nullable();
            $table->text('teacher_issue')->nullable();
            $table->text('discussion_content')->nullable();
            $table->text('solution_plan')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_onsite_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->unsignedTinyInteger('observe_unit')->nullable();
            $table->unsignedTinyInteger('observe_lesson')->nullable();
            $table->string('observe_summary_extra', 255)->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->text('other_notes')->nullable();
            $table->json('procedures')->nullable();
            $table->json('strength_areas')->nullable();
            $table->json('growth_areas')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_littleseed_con_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->json('procedures')->nullable();
            $table->text('teacher_issue')->nullable();
            $table->text('discussion_content')->nullable();
            $table->text('solution_plan')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_ls_onsite_lva_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->unsignedTinyInteger('observe_set')->nullable();
            $table->unsignedTinyInteger('observe_day')->nullable();
            $table->string('observe_summary_extra', 255)->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->text('other_notes')->nullable();
            $table->unsignedSmallInteger('lesson_length_minutes')->nullable();
            $table->json('procedures')->nullable();
            $table->text('teacher_strengths')->nullable();
            $table->text('areas_of_concerns')->nullable();
            $table->text('next_step')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_lva_fb_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->unsignedTinyInteger('observe_unit')->nullable();
            $table->unsignedTinyInteger('observe_lesson')->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->text('other_notes')->nullable();
            $table->unsignedSmallInteger('video_length_minutes')->nullable();
            $table->json('procedures')->nullable();
            $table->json('strength_areas')->nullable();
            $table->json('growth_areas')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_lva_fr_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->unsignedTinyInteger('observe_unit')->nullable();
            $table->unsignedTinyInteger('observe_lesson')->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->string('teacher_experience', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('method', 50)->nullable();
            $table->text('other_notes')->nullable();
            $table->unsignedSmallInteger('video_length_minutes')->nullable();
            $table->json('procedures')->nullable();
            $table->json('strength_areas')->nullable();
            $table->json('growth_areas')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_demo_lesson_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->unsignedTinyInteger('progress_unit')->nullable();
            $table->unsignedTinyInteger('progress_lesson')->nullable();
            $table->text('other_notes')->nullable();
            $table->json('procedures')->nullable();
            $table->json('verbal_tools')->nullable();
            $table->json('language_arts_tools')->nullable();
            $table->text('comments_primary')->nullable();
            $table->text('comments_secondary')->nullable();
            $table->json('evaluations')->nullable();
            $table->text('overall_comments')->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'team' => 'CO',
        ]);
    }

    private function createCoachUser(string $name = 'Coach User', string $email = 'coach@example.com'): User
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
        return \DB::table('Teachers')->insertGetId(array_merge([
            'SK_Code' => $skCode,
            'Name' => $name,
            'ClassInOut' => true,
        ], $extra));
    }

    public function test_admin_sees_all_teachers(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', ['Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('김교사')
            ->assertSee('이교사');
    }

    public function test_coach_only_sees_tr_scoped_teachers(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', ['Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('김교사')
            ->assertDontSee('이교사');
    }

    public function test_coach_with_no_alias_sees_nothing(): void
    {
        $coach = $this->createCoachUser('Unknown Coach', 'unknown@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '김교사', ['Plan_1st_Support_Date' => "{$year}-03-01"]);

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->assertDontSee('김교사')
            ->assertSee('조건에 맞는 교사가 없습니다');
    }

    public function test_kpi_counts_match_displayed_data(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');

        $this->createTeacher('SK001', '교사1', [
            '_1st_Support_Date' => "{$year}-03-10",
            '_2nd_Support_Date' => "{$year}-05-15",
            'Plan_1st_Support_Date' => "{$year}-03-01",
            'Plan_2nd_Support_Date' => "{$year}-05-01",
        ]);

        $this->createTeacher('SK001', '교사2', [
            '_1st_Support_Date' => "{$year}-04-10",
            'Plan_1st_Support_Date' => "{$year}-04-01",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);

        $this->createTeacher('SK001', '교사3', [
            'Plan_1st_Support_Date' => "{$year}-05-01",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class);

        $kpis = $component->viewData('kpis');

        $this->assertSame(2, $kpis['first_round']);
        $this->assertSame(1, $kpis['second_round']);
        $this->assertSame(1, $kpis['completed']);
        $this->assertSame(2, $kpis['unsupported']);
    }

    public function test_kpi_filter_click_filters_table(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');

        $this->createTeacher('SK001', '완료교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            '_2nd_Support_Date' => "{$year}-05-15",
            'Plan_1st_Support_Date' => "{$year}-03-01",
            'Plan_2nd_Support_Date' => "{$year}-05-01",
        ]);

        $this->createTeacher('SK001', '미지원교사', [
            'Plan_1st_Support_Date' => "{$year}-05-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('setKpiFilter', 'completed')
            ->assertSee('완료교사')
            ->assertDontSee('미지원교사');
    }

    public function test_filter_year_changes_kpi(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');

        $this->createTeacher('SK001', '교사1', [
            '_1st_Support_Date' => '2025-03-10',
            'Plan_1st_Support_Date' => '2025-03-01',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', 2025);

        $kpis = $component->viewData('kpis');
        $this->assertSame(1, $kpis['first_round']);

        $component->set('filterYear', 2024);
        $kpis = $component->viewData('kpis');
        $this->assertSame(0, $kpis['first_round']);
    }

    public function test_hidden_institution_excluded(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', ['Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-03-01"]);

        \DB::table('institution_visibility_overrides')->insert([
            'sk_code' => 'SK002',
            'hidden_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('김교사')
            ->assertDontSee('이교사');
    }

    public function test_search_filters_results(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '김교사', ['Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK001', '박선생', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('search', '김교사')
            ->assertSee('김교사')
            ->assertDontSee('박선생');
    }

    public function test_class_out_teachers_shown_by_default(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '수업참여교사', ['ClassInOut' => true, 'Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK001', '수업미참여교사', ['ClassInOut' => false, 'Plan_1st_Support_Date' => "{$year}-03-01"]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('수업참여교사')
            ->assertSee('수업미참여교사');
    }

    public function test_retired_teacher_hidden_regardless_of_class_in_out(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '수업참여교사', ['ClassInOut' => true, 'Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK001', '퇴직이지만참여', [
            'Status' => '퇴직',
            'ClassInOut' => true,
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('수업참여교사')
            ->assertDontSee('퇴직이지만참여')
            ->set('search', '퇴직이지만참여')
            ->assertDontSee('퇴직이지만참여');
    }

    public function test_show_all_teachers_includes_retired(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '수업참여교사', ['ClassInOut' => true, 'Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK001', '수업미참여교사', ['ClassInOut' => false, 'Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK001', '퇴직교사', [
            'Status' => '퇴직',
            'ClassInOut' => false,
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('수업참여교사')
            ->assertSee('수업미참여교사')
            ->assertDontSee('퇴직교사')
            ->set('showAllTeachers', true)
            ->assertSee('퇴직교사');
    }

    public function test_retired_teacher_position_badge_uses_red_background(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '퇴직교사', [
            'Status' => '퇴직',
            'Position' => '교사',
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('showAllTeachers', true);

        $this->assertStringContainsString('bg-red-100 text-red-800', $component->html());
    }

    public function test_kpis_follow_teacher_list_visibility_filter(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '수업참여완료', [
            'ClassInOut' => true,
            '_1st_Support_Date' => "{$year}-03-01",
        ]);
        $this->createTeacher('SK001', '수업미참여완료', [
            'ClassInOut' => false,
            '_1st_Support_Date' => "{$year}-03-01",
        ]);
        $this->createTeacher('SK001', '퇴직완료', [
            'Status' => '퇴직',
            'ClassInOut' => true,
            '_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['first_round'] === 2)
            ->set('showAllTeachers', true)
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['first_round'] === 3);
    }

    public function test_page_loads_for_unauthenticated_user(): void
    {
        $this->get(route('coach.teacher-support.index'))
            ->assertRedirect();
    }

    public function test_list_sorted_by_institution_name_korean(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK002', '나나유치원', 'Coach A');
        $this->createInstitution('SK001', '가가유치원', 'Coach A');
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK001', '김교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class);

        $teachers = $component->viewData('teachers');
        $names = collect($teachers->items())->pluck('Name')->values()->all();

        $this->assertSame('김교사', $names[0]);
        $this->assertSame('이교사', $names[1]);
    }

    public function test_month_filter_uses_plan_dates(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '3월계획', ['Plan_1st_Support_Date' => "{$year}-03-01"]);
        $this->createTeacher('SK001', '5월계획', ['Plan_2nd_Support_Date' => "{$year}-05-01", 'Plan_1st_Support_Date' => "{$year}-01-01"]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterMonth', '3')
            ->assertSee('3월계획')
            ->assertDontSee('5월계획');
    }

    public function test_save_edit_form_updates_teacher(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '김교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openEditModal', $id)
            ->set('editForm.completed_1st', "{$year}-03-15")
            ->set('editForm.type_1st', '방문')
            ->call('saveEditForm')
            ->assertHasNoErrors();

        $teacher = Teacher::find($id);
        $cols = config('coach_teacher_support.columns');
        $this->assertSame("{$year}-03-15", $teacher->{$cols['completed_1st']}->format('Y-m-d'));
        $this->assertSame('방문', $teacher->{$cols['type_1st']});
    }

    public function test_retired_teacher_modals_blocked_until_inactive_filter_enabled(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '퇴직대상', [
            'Status' => '퇴직',
            'ClassInOut' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openEditModal', $id)
            ->assertSet('showEditModal', false)
            ->call('openTeacherModal', $id)
            ->assertSet('showTeacherModal', false)
            ->call('openDemoLessonModal', $id)
            ->assertSet('showDemoLessonModal', false)
            ->set('showAllTeachers', true)
            ->call('openTeacherModal', $id)
            ->assertSet('showTeacherModal', true)
            ->call('openEditModal', $id)
            ->assertSet('showEditModal', true);
    }

    public function test_coach_cannot_edit_outside_tr_scope(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $id = $this->createTeacher('SK002', '이교사');

        $component = Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class);

        $component->call('openEditModal', $id);

        $this->assertFalse($component->get('showEditModal'));
    }

    public function test_action_rejects_unauthorized_user(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');

        $this->createInstitution('SK001', '기관A', 'Coach B');
        $id = $this->createTeacher('SK001', '이교사');

        $action = new UpdateTeacherSupport;

        $this->expectException(AuthorizationException::class);

        $action->execute($id, ['completed_1st' => '2026-05-01'], $coach);
    }

    public function test_essentials_null_allowed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '김교사', [
            'GrapeSEEDEssentials' => '2025-06-01',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openEditModal', $id)
            ->set('editForm.essentials_gs', '')
            ->call('saveEditForm')
            ->assertHasNoErrors();

        $teacher = Teacher::find($id);
        $this->assertNull($teacher->GrapeSEEDEssentials);
    }

    public function test_teacher_name_opens_teacher_modal(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동', [
            'Email' => 'hong@test.com',
            'Phone' => '010-1234-5678',
            'Position' => '원장',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id);

        $this->assertTrue($component->get('showTeacherModal'));
        $this->assertSame('홍길동', $component->get('teacherDetailInfo.name'));
        $this->assertSame('hong@test.com', $component->get('teacherDetailInfo.email'));
        $this->assertSame('010-1234-5678', $component->get('teacherDetailInfo.phone'));
        $this->assertSame([], $component->get('teacherDetailHistory'));
    }

    public function test_teacher_modal_shows_legacy_support_history_from_specialty_tables(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        \Illuminate\Support\Facades\DB::table('S_Support_NewTeacher')->insert([
            'TR_Name' => 'Selly Kim',
            'SK_Code' => 'SK001',
            'Teacher' => '홍길동',
            'TeacherId' => $id,
            'SupportDate' => '2024-02-17 00:00:00',
            'Status' => '완료',
            'ReportType' => 1,
        ]);

        \Illuminate\Support\Facades\DB::table('S_Support_LVA')->insert([
            'TR_Name' => 'Christie Jung',
            'SK_Code' => 'SK001',
            'Teacher' => '홍길동',
            'TeacherId' => $id,
            'SupportDate' => '2024-12-17 00:00:00',
            'Status' => '완료',
            'ReportType' => 3,
            'LVA_TYPE' => 'FB',
        ]);

        \Illuminate\Support\Facades\DB::table('S_Support_OnSite')->insert([
            'TR_Name' => 'Christie Jung',
            'SK_Code' => 'SK001',
            'Teacher' => '홍길동',
            'TeacherId' => $id,
            'SupportDate' => '2025-06-30 00:00:00',
            'Status' => '완료',
        ]);

        $history = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id)
            ->get('teacherDetailHistory');

        $this->assertCount(3, $history);
        $types = collect($history)->pluck('type')->all();
        $this->assertContains('교사 지원(신규교사)', $types);
        $this->assertContains('교사 지원 LVA FB', $types);
        $this->assertContains('교사 지원 On-Site', $types);
    }

    public function test_teacher_list_and_institution_modal_prefer_s_account_information_account_name(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        \DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-NAME-PRIORITY',
            'AccountName' => '레거시 FLS 이름',
        ]);
        \DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-NAME-PRIORITY',
            'Account_Name' => '마스터 기관명',
            'TR' => 'Coach A',
        ]);
        $this->createTeacher('SK-NAME-PRIORITY', '김교사', [
            'School_Name' => '교사 테이블 학교명',
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('마스터 기관명')
            ->assertDontSee('레거시 FLS 이름')
            ->assertDontSee('교사 테이블 학교명')
            ->call('openInstitutionModal', 'SK-NAME-PRIORITY')
            ->assertSet('institutionInfo.name', '마스터 기관명');
    }

    public function test_institution_modal_loads_contacts_for_sk_code(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '홍길동', [
            'Phone' => '010-1111-2222',
            'Email' => 'hong@example.com',
            '_1st_Support_Date' => '2025-06-30',
            '_1st_Support_Type' => 'On-Site',
        ]);
        $this->createTeacher('SK001', '김영희', [
            'Phone' => '010-3333-4444',
            '_2nd_Support_Date' => '2025-10-28',
            '_2nd_Support_Type' => 'Open-Class',
        ]);

        $contacts = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK001')
            ->get('institutionContacts');

        $this->assertCount(2, $contacts);
        $hong = collect($contacts)->firstWhere('name', '홍길동');
        $kim = collect($contacts)->firstWhere('name', '김영희');
        $this->assertNotNull($hong);
        $this->assertSame('2025-06-30', $hong['last_support_date']);
        $this->assertSame('On-Site', $hong['last_support_type']);
        $this->assertSame('2025-10-28', $kim['last_support_date']);
        $this->assertSame('Open-Class', $kim['last_support_type']);
    }

    public function test_institution_modal_contacts_follow_teacher_list_visibility_filter(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '재직연락처');
        $this->createTeacher('SK001', '퇴직연락처', [
            'Status' => '퇴직',
            'ClassInOut' => true,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class);

        $contacts = $component
            ->call('openInstitutionModal', 'SK001')
            ->get('institutionContacts');

        $this->assertCount(1, $contacts);
        $this->assertSame('재직연락처', $contacts[0]['name']);

        $contactsWithRetired = $component
            ->set('showAllTeachers', true)
            ->call('openInstitutionModal', 'SK001')
            ->get('institutionContacts');

        $this->assertCount(2, $contactsWithRetired);
        $this->assertSame(
            ['재직연락처', '퇴직연락처'],
            collect($contactsWithRetired)->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_teacher_support_history_row_opens_detail_modal(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        \Illuminate\Support\Facades\DB::table('S_Support_NewTeacher')->insert([
            'TR_Name' => 'Selly Kim',
            'SK_Code' => 'SK001',
            'Teacher' => '홍길동',
            'TeacherId' => $id,
            'SupportDate' => '2024-02-17 00:00:00',
            'Status' => '완료',
            'ReportType' => 1,
        ]);

        $legacyId = (int) \Illuminate\Support\Facades\DB::table('S_Support_NewTeacher')->max('ID');
        $detailKey = 'legacy:S_Support_NewTeacher:'.$legacyId;

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $id)
            ->assertSet('supportReportViewMode', true)
            ->assertSet('showDemoLessonModal', true)
            ->assertSet('demoLessonForm.coach_name', 'Selly Kim');
    }

    public function test_teacher_name_click_does_not_open_edit_modal(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id);

        $this->assertTrue($component->get('showTeacherModal'));
        $this->assertFalse($component->get('showEditModal'));
    }

    public function test_teacher_modal_sk_code_fallback(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK1417', '스타기관', 'Coach Star');
        $id = $this->createTeacher('*SK1417', '별교사', [
            'School_Name' => '스타기관',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id);

        $this->assertTrue($component->get('showTeacherModal'));
        $this->assertSame('SK1417', $component->get('teacherDetailInfo.sk_code'));
        $this->assertSame('Coach Star', $component->get('teacherDetailInfo.tr'));
    }

    public function test_save_teacher_profile_updates_fields(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '김교사', [
            'Email' => 'old@test.com',
            'Phone' => '010-0000-0000',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id)
            ->call('startTeacherEdit')
            ->set('teacherProfileForm.name', '김수정')
            ->set('teacherProfileForm.email', 'new@test.com')
            ->call('saveTeacherProfile')
            ->assertHasNoErrors();

        $teacher = Teacher::find($id);
        $this->assertSame('김수정', $teacher->Name);
        $this->assertSame('new@test.com', $teacher->Email);
    }

    public function test_retire_teacher_sets_class_in_out_false(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '퇴직대상', ['ClassInOut' => true]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id)
            ->call('confirmRetireTeacher')
            ->call('retireTeacher');

        $teacher = Teacher::find($id);
        $this->assertFalse($teacher->ClassInOut);
        $this->assertSame('퇴직', $teacher->Status);

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $id,
            'Status' => '퇴직',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertDontSee('퇴직대상');
    }

    public function test_coach_cannot_edit_teacher_outside_scope(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $id = $this->createTeacher('SK002', '이교사');

        $component = Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id);

        $this->assertFalse($component->get('showTeacherModal'));
    }

    public function test_demo_lesson_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openDemoLessonModal', $id);

        $this->assertTrue($component->get('showDemoLessonModal'));
        $this->assertSame('홍길동', $component->get('demoLessonForm.teacher_name'));
        $this->assertSame('기관A', $component->get('demoLessonForm.institution_name'));
    }

    public function test_save_demo_lesson_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openDemoLessonModal', $id)
            ->set('demoLessonForm.support_date', '2026-05-19')
            ->set('demoLessonForm.overall_comments', '전체 코멘트')
            ->set('demoLessonMarkCompleted', true)
            ->call('saveDemoLessonReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_demo_lesson_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => '신규교사 시연수업',
            'Status' => '완료',
        ]);
    }

    public function test_lva_fr_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLvaFrModal', $id);

        $this->assertTrue($component->get('showLvaFrModal'));
        $this->assertSame('홍길동', $component->get('lvaFrForm.teacher_name'));
        $this->assertSame('기관A', $component->get('lvaFrForm.institution_name'));
        $this->assertSame('화상', $component->get('lvaFrForm.method'));
    }

    public function test_save_lva_fr_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLvaFrModal', $id)
            ->set('lvaFrForm.support_date', '2026-05-19')
            ->set('lvaFrForm.interview_date', '2026-05-19')
            ->set('lvaFrForm.interview_time', '13:00')
            ->set('lvaFrForm.other_notes', '추가 메모')
            ->set('lvaFrMarkCompleted', true)
            ->call('saveLvaFrReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_lva_fr_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'LVA + FR',
            'Status' => '완료',
        ]);
    }

    public function test_lva_fb_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLvaFbModal', $id);

        $this->assertTrue($component->get('showLvaFbModal'));
        $this->assertSame('홍길동', $component->get('lvaFbForm.teacher_name'));
        $this->assertSame('기관A', $component->get('lvaFbForm.institution_name'));
        $this->assertSame('화상', $component->get('lvaFbForm.method'));
    }

    public function test_save_lva_fb_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLvaFbModal', $id)
            ->set('lvaFbForm.support_date', '2026-05-19')
            ->set('lvaFbForm.interview_date', '2026-05-19')
            ->set('lvaFbForm.interview_time', '13:00')
            ->set('lvaFbForm.other_notes', '참관 기타 메모')
            ->set('lvaFbMarkCompleted', true)
            ->call('saveLvaFbReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_lva_fb_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'other_notes' => '참관 기타 메모',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'LVA + FB',
            'Status' => '완료',
        ]);
    }

    public function test_ls_onsite_lva_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLsOnsiteLvaModal', $id);

        $this->assertTrue($component->get('showLsOnsiteLvaModal'));
        $this->assertSame('홍길동', $component->get('lsOnsiteLvaForm.teacher_name'));
        $this->assertSame('', $component->get('lsOnsiteLvaForm.teacher_strengths'));
    }

    public function test_save_ls_onsite_lva_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLsOnsiteLvaModal', $id)
            ->set('lsOnsiteLvaForm.support_date', '2026-05-19')
            ->set('lsOnsiteLvaForm.observe_set', 2)
            ->set('lsOnsiteLvaForm.observe_day', 3)
            ->set('lsOnsiteLvaForm.teacher_strengths', '발음이 좋음')
            ->set('lsOnsiteLvaForm.next_step', '다음 유닛 연습')
            ->set('lsOnsiteLvaMarkCompleted', true)
            ->call('saveLsOnsiteLvaReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_ls_onsite_lva_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'observe_set' => 2,
            'observe_day' => 3,
            'teacher_strengths' => '발음이 좋음',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'LS On-Site & LVA',
            'Status' => '완료',
        ]);
    }

    public function test_littleseed_con_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLittleseedConModal', $id);

        $this->assertTrue($component->get('showLittleseedConModal'));
        $this->assertSame('홍길동', $component->get('littleseedConForm.teacher_name'));
        $this->assertSame('화상', $component->get('littleseedConForm.method'));
    }

    public function test_save_littleseed_con_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLittleseedConModal', $id)
            ->set('littleseedConForm.support_date', '2026-05-19')
            ->set('littleseedConForm.teacher_issue', '수업 진도 이슈')
            ->set('littleseedConForm.discussion_content', '토론 내용')
            ->set('littleseedConForm.solution_plan', '다음 주 재점검')
            ->set('littleseedConMarkCompleted', true)
            ->call('saveLittleseedConReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_littleseed_con_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'teacher_issue' => '수업 진도 이슈',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'LittleSEED Con',
            'Status' => '완료',
        ]);
    }

    public function test_onsite_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openOnsiteModal', $id);

        $this->assertTrue($component->get('showOnsiteModal'));
        $this->assertSame('홍길동', $component->get('onsiteForm.teacher_name'));
        $this->assertSame('대면', $component->get('onsiteForm.method'));
    }

    public function test_save_onsite_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openOnsiteModal', $id)
            ->set('onsiteForm.support_date', '2026-05-19')
            ->set('onsiteForm.observe_unit', 3)
            ->set('onsiteForm.strength_areas', ['teacher_planning'])
            ->set('onsiteMarkCompleted', true)
            ->call('saveOnsiteReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_onsite_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'observe_unit' => 3,
            'method' => '대면',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'On-Site',
            'Status' => '완료',
        ]);
    }

    public function test_pro_con_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openProConModal', $id);

        $this->assertTrue($component->get('showProConModal'));
        $this->assertSame('홍길동', $component->get('proConForm.teacher_name'));
    }

    public function test_save_pro_con_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openProConModal', $id)
            ->set('proConForm.support_date', '2026-05-19')
            ->set('proConForm.teacher_issue', '수업 운영 이슈')
            ->set('proConForm.solution_plan', '주간 체크인')
            ->set('proConMarkCompleted', true)
            ->call('saveProConReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_pro_con_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'teacher_issue' => '수업 운영 이슈',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'Pro Con',
            'Status' => '완료',
        ]);
    }

    public function test_open_class_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openOpenClassModal', $id);

        $this->assertTrue($component->get('showOpenClassModal'));
        $this->assertSame('홍길동', $component->get('openClassForm.teacher_name'));
    }

    public function test_save_open_class_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openOpenClassModal', $id)
            ->set('openClassForm.support_date', '2026-05-19')
            ->set('openClassForm.progress_unit', 3)
            ->set('openClassForm.progress_lesson', 2)
            ->set('openClassForm.remarks', '오픈클래스 지원 메모')
            ->set('openClassMarkCompleted', true)
            ->call('saveOpenClassReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_open_class_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'progress_unit' => 3,
            'remarks' => '오픈클래스 지원 메모',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'Open-Class',
            'Status' => '완료',
        ]);
    }

    public function test_unit21_plus_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openUnit21PlusModal', $id);

        $this->assertTrue($component->get('showUnit21PlusModal'));
        $this->assertSame('홍길동', $component->get('unit21PlusForm.teacher_name'));
    }

    public function test_save_unit21_plus_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openUnit21PlusModal', $id)
            ->set('unit21PlusForm.support_date', '2026-05-19')
            ->set('unit21PlusForm.progress_unit', 21)
            ->set('unit21PlusForm.overall_comments', 'U21 트레이닝 완료')
            ->set('unit21PlusMarkCompleted', true)
            ->call('saveUnit21PlusReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_unit21_plus_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'progress_unit' => 21,
            'overall_comments' => 'U21 트레이닝 완료',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'Unit 21+',
            'Status' => '완료',
        ]);
    }

    public function test_unit31_plus_modal_opens_from_teacher_detail(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openUnit31PlusModal', $id);

        $this->assertTrue($component->get('showUnit31PlusModal'));
        $this->assertSame('홍길동', $component->get('unit31PlusForm.teacher_name'));
    }

    public function test_save_unit31_plus_report_creates_record_and_support_when_completed(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openUnit31PlusModal', $id)
            ->set('unit31PlusForm.support_date', '2026-05-19')
            ->set('unit31PlusForm.progress_unit', 31)
            ->set('unit31PlusForm.overall_comments', 'U31 트레이닝 완료')
            ->set('unit31PlusMarkCompleted', true)
            ->call('saveUnit31PlusReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_unit31_plus_support_reports', [
            'teacher_id' => $id,
            'teacher_name' => '홍길동',
            'progress_unit' => 31,
            'overall_comments' => 'U31 트레이닝 완료',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Target' => '홍길동',
            'Support_Type' => 'Unit 31+',
            'Status' => '완료',
        ]);
    }
}
