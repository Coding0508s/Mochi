<?php

namespace Tests\Feature;

use App\Actions\UpdateTeacherSupport;
use App\Enums\TeacherEmploymentType;
use App\Livewire\CoachTeacherSupportList;
use App\Models\Teacher;
use App\Models\User;
use App\Support\ExcelSerialDate;
use App\Support\TeacherSupportKpiCalculator;
use Carbon\Carbon;
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

    protected function createRequiredTables(): void
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
        Schema::dropIfExists('teacher_visit_support_reports');
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
            $table->string('Customer_Type', 255)->nullable();
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
            $table->string('EmploymentType', 32)->default('unspecified');
            $table->boolean('ClassInOut')->default(true);
            $table->dateTime('Created_Date')->nullable();
            $table->date('Plan_1st_Support_Date')->nullable();
            $table->date('Plan_2nd_Support_Date')->nullable();
            $table->date('Plan_3rd_Support_Date')->nullable();
            $table->date('Plan_4th_Support_Date')->nullable();
            $table->string('Plan_1st_Support_Type', 100)->nullable();
            $table->string('Plan_2nd_Support_Type', 100)->nullable();
            $table->string('Plan_3rd_Support_Type', 100)->nullable();
            $table->string('Plan_4th_Support_Type', 100)->nullable();
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
            $table->unsignedTinyInteger('STATUS')->default(1);
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
            $table->string('Account_Name', 255)->nullable();
            $table->string('Teacher', 255)->nullable();
            $table->unsignedInteger('TeacherId')->nullable();
            $table->dateTime('SupportDate')->nullable();
            $table->string('Status', 50)->nullable();
            $table->text('Other')->nullable();
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

        Schema::create('teacher_visit_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('support_location', 255)->nullable();
            $table->string('support_purpose', 100);
            $table->unsignedTinyInteger('observe_unit')->nullable();
            $table->unsignedTinyInteger('observe_lesson')->nullable();
            $table->string('observe_summary_extra', 255)->nullable();
            $table->string('observe_class', 50)->nullable();
            $table->string('observe_age', 50)->nullable();
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('semester_label', 100)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_time', 10)->nullable();
            $table->string('meeting_type', 50)->nullable();
            $table->text('pre_request_notes')->nullable();
            $table->text('monitoring_feedback')->nullable();
            $table->text('interview_and_action_plan')->nullable();
            $table->text('special_notes')->nullable();
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

    protected function createAdminUser(): User
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

    protected function createInstitution(string $skCode, string $name, ?string $tr = null, ?string $customerType = null): void
    {
        \DB::table('S_AccountName')->insert([
            'SKcode' => $skCode,
            'AccountName' => $name,
        ]);

        if ($tr !== null || $customerType !== null) {
            \DB::table('S_Account_Information')->insert([
                'SK_Code' => $skCode,
                'Account_Name' => $name,
                'TR' => $tr,
                'Customer_Type' => $customerType,
            ]);
        }

        if ($tr !== null) {
            // Ensure employee exists for the TR so they appear in the filter
            if (! \DB::table('employee')->where('ENGLISHNAME', $tr)->orWhere('KOREANAME', $tr)->exists()) {
                \DB::table('employee')->insert([
                    'EMPNO' => 'EMP'.rand(1000, 9999).uniqid(),
                    'ENGLISHNAME' => $tr,
                    'STATUS' => 1,
                ]);
            }
        }
    }

    private function createAccountInformationOnly(
        string $skCode,
        string $name,
        ?string $tr = null,
        ?string $customerType = null,
    ): void {
        \DB::table('S_Account_Information')->insert([
            'SK_Code' => $skCode,
            'Account_Name' => $name,
            'TR' => $tr,
            'Customer_Type' => $customerType,
        ]);
    }

    protected function createTeacher(string $skCode, string $name, array $extra = [], bool $forLatestView = true): int
    {
        if ($forLatestView) {
            $extra = $this->withLatestViewSupportHistory($extra);
        }

        return \DB::table('Teachers')->insertGetId(array_merge([
            'SK_Code' => $skCode,
            'Name' => $name,
            'ClassInOut' => true,
            'Position' => '교사',
        ], $extra));
    }

    /**
     * 최신 지원 보기(기본) 목록에는 완료·MOCHI가 있는 교사만 노출된다.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function withLatestViewSupportHistory(array $extra): array
    {
        $completionColumns = [
            '_1st_Support_Date',
            '_2nd_Support_Date',
            '_3rd_Support_Date',
            '_4th_Support_Date',
        ];

        foreach ($completionColumns as $column) {
            if (filled($extra[$column] ?? null)) {
                return $extra;
            }
        }

        $planDate = $extra['Plan_1st_Support_Date']
            ?? $extra['Plan_2nd_Support_Date']
            ?? $extra['Plan_3rd_Support_Date']
            ?? $extra['Plan_4th_Support_Date']
            ?? null;

        if ($planDate === null) {
            $extra['_1st_Support_Date'] = now()->format('Y-m-d');

            return $extra;
        }

        $extra['_1st_Support_Date'] = $this->normalizeTeacherSupportDateForTests($planDate);

        return $extra;
    }

    private function normalizeTeacherSupportDateForTests(mixed $value): string
    {
        return ExcelSerialDate::toStorageString($value) ?? now()->format('Y-m-d');
    }

    public function test_admin_sees_all_teachers(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
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
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('김교사')
            ->assertDontSee('이교사');
    }

    public function test_admin_coach_filter_narrows_teacher_list_and_kpis(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->set('filterCoach', 'Coach A')
            ->assertSee('김교사')
            ->assertDontSee('이교사');

        $names = collect($component->viewData('teachers')->items())->pluck('Name')->all();
        $this->assertSame(['김교사'], $names);
        $this->assertSame(1, $component->viewData('kpis')['unsupported']);
    }

    public function test_coach_can_filter_by_other_coach_tr(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->set('filterCoach', 'Coach B')
            ->assertSet('filterCoach', 'Coach B')
            ->assertDontSee('김교사')
            ->assertSee('이교사');
    }

    public function test_coach_filter_options_only_include_visible_coaches(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        $options = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->viewData('coachFilterOptions');

        $this->assertEqualsCanonicalizing(['Coach A', 'Coach B'], $options->all());
    }

    public function test_coach_can_clear_filter_to_view_all_coaches(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
        $this->createTeacher('SK002', '이교사', ['Plan_1st_Support_Date' => "{$year}-04-01"]);

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('김교사')
            ->assertDontSee('이교사')
            ->set('filterCoach', '')
            ->assertSee('김교사')
            ->assertSee('이교사');
    }

    public function test_coach_mount_defaults_filter_to_current_year_and_logged_in_coach(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '내담당교사', [
            '_1st_Support_Date' => "{$year}-05-10",
        ]);
        $this->createTeacher('SK002', '타담당교사', [
            '_1st_Support_Date' => "{$year}-05-11",
        ]);

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->assertSet('filterYear', (string) $year)
            ->assertSet('filterCoach', 'Coach A')
            ->assertSee('내담당교사')
            ->assertDontSee('타담당교사');
    }

    public function test_institution_toggle_labels_are_not_rendered(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '교사1', [
            '_1st_Support_Date' => "{$year}-05-10",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertDontSee('최신 지원 보기')
            ->assertDontSee('전체 기관 보기')
            ->assertSee('전체 기관 기준 조회');
    }

    public function test_kpi_summary_renders_three_group_dividers(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '교사1', [
            '_1st_Support_Date' => "{$year}-05-10",
        ]);

        $html = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->html();

        $this->assertSame(3, substr_count($html, 'data-kpi-divider'));
    }

    public function test_teacher_pagination_uses_teacher_count_even_with_same_institution(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        for ($index = 1; $index <= 49; $index++) {
            $skCode = sprintf('SK%03d', $index);
            $teacherName = sprintf('기본교사%03d', $index);
            $this->createInstitution($skCode, "기관{$index}", 'Coach A');
            $this->createTeacher($skCode, $teacherName, [
                '_1st_Support_Date' => "{$year}-06-01",
            ]);
        }

        $this->createInstitution('SK999', '경계기관', 'Coach A');
        $this->createTeacher('SK999', '경계교사A', [
            '_1st_Support_Date' => "{$year}-05-02",
        ]);
        $this->createTeacher('SK999', '경계교사B', [
            '_1st_Support_Date' => "{$year}-05-01",
        ]);

        $firstPageComponent = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year);

        $firstPageNames = collect($firstPageComponent->viewData('teachers')->items())->pluck('Name')->all();
        $this->assertContains('경계교사A', $firstPageNames);
        $this->assertNotContains('경계교사B', $firstPageNames);

        $secondPageComponent = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->call('setPage', 2);

        $secondPageNames = collect($secondPageComponent->viewData('teachers')->items())->pluck('Name')->all();
        $this->assertSame(['경계교사B'], $secondPageNames);

        $groupPaginator = $secondPageComponent->viewData('institutionGroupPaginator');
        $this->assertSame(51, $groupPaginator->firstItem());
        $this->assertSame(51, $groupPaginator->lastItem());
        $this->assertSame(51, $groupPaginator->total());
        $this->assertLessThanOrEqual($groupPaginator->total(), $groupPaginator->lastItem());
        $this->assertSame(1, $secondPageComponent->viewData('teachers')->count());
        $secondPageComponent->assertSee('51–51')
            ->assertSee('전체 51명')
            ->assertSee('이번 페이지 교사 1명')
            ->assertDontSee('Showing 51 to 52');
    }

    public function test_all_institutions_view_orders_by_latest_support_date_with_unsupported_last(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '한국기관', 'Coach A');
        $this->createInstitution('SK002', '가나기관', 'Coach A');

        // 더 오래된 지원 — 기관명으로는 '가나'가 앞이지만, 최신 지원순이면 뒤
        $this->createTeacher('SK002', '이교사', [
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        // 더 최근 지원
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-05-21",
        ]);
        // 계획만 있고 완료 없음 — 연도 스코프에는 포함되고 정렬은 맨 뒤
        $this->createTeacher('SK001', '미지원교사', [
            'Plan_1st_Support_Date' => "{$year}-04-01",
        ], forLatestView: false);

        $names = collect(
            Livewire::actingAs($admin)
                ->test(CoachTeacherSupportList::class)
                ->set('filterYear', $year)
                ->viewData('teachers')
                ->items()
        )->pluck('Name')->all();

        $this->assertSame(['김교사', '이교사', '미지원교사'], $names);
    }

    public function test_teachers_in_same_institution_are_sorted_by_each_latest_support_date(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '용인 시립숲속하나어린이집', 'Coach A');
        $this->createInstitution('SK002', '광명 올어바웃본더랜드', 'Coach A');

        $this->createTeacher('SK001', '정현진', [
            '_1st_Support_Date' => "{$year}-07-28",
        ]);
        $this->createTeacher('SK001', '박도연', [
            '_1st_Support_Date' => "{$year}-05-20",
        ]);
        $this->createTeacher('SK002', '비앙카', [
            '_1st_Support_Date' => "{$year}-07-24",
        ]);

        $names = collect(
            Livewire::actingAs($admin)
                ->test(CoachTeacherSupportList::class)
                ->set('filterYear', $year)
                ->viewData('teachers')
                ->items()
        )->pluck('Name')->all();

        $this->assertSame(['정현진', '비앙카', '박도연'], $names);
    }

    public function test_unsupported_section_banner_is_not_rendered(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-05-21",
        ]);
        $this->createTeacher('SK001', '미지원교사', [
            'Plan_1st_Support_Date' => "{$year}-04-01",
        ], forLatestView: false);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->assertDontSee('미지원 교사 목록 (기관별)');
    }

    public function test_all_institutions_view_does_not_duplicate_teachers_when_account_name_rows_duplicated(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        // 운영 DB의 S_AccountName에는 같은 SKcode가 대소문자 변형으로 중복 존재한다(예: Sk2844/SK2844).
        // join 방식이면 MySQL(대소문자 무시 collation)에서 교사 행이 복제된다.
        \DB::table('S_AccountName')->insert([
            'SKcode' => 'Sk001',
            'AccountName' => '기관A',
        ]);
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year);

        $names = collect($component->viewData('teachers')->items())->pluck('Name')->all();

        $this->assertSame(['김교사'], $names);
        $this->assertSame(1, $component->viewData('teachers')->total());
    }

    public function test_latest_support_view_orders_by_latest_support_date(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '먼저완료', [
            '_1st_Support_Date' => "{$year}-03-01",
            'Created_Date' => '2026-06-01 10:00:00',
        ]);
        $this->createTeacher('SK001', '나중완료', [
            '_1st_Support_Date' => "{$year}-05-21",
            'Created_Date' => '2025-01-01 10:00:00',
        ]);

        $names = collect(
            Livewire::actingAs($admin)
                ->test(CoachTeacherSupportList::class)
                ->set('filterYear', $year)
                ->viewData('teachers')
                ->items()
        )->pluck('Name')->all();

        $this->assertSame(['나중완료', '먼저완료'], $names);
    }

    public function test_current_year_filter_includes_teachers_without_support_history(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '지원있음', ['_1st_Support_Date' => "{$year}-05-21"]);
        $this->createTeacher('SK001', '지원없음', forLatestView: false);
        $this->createTeacher('SK001', '계획만', ['Plan_1st_Support_Date' => "{$year}-05-01"], forLatestView: false);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->assertSee('지원있음')
            ->assertSee('지원없음')
            ->assertSee('계획만')
            ->set('filterYear', '')
            ->assertSee('지원있음')
            ->assertSee('지원없음')
            ->assertSee('계획만');
    }

    public function test_latest_support_view_includes_teacher_with_mochi_report_only(): void
    {
        $admin = $this->createAdminUser();
        $year = 2026;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '참관교사', forLatestView: false);

        \DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '참관교사',
            'support_date' => '2026-06-18',
            'support_purpose' => '신임',
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->assertSee('참관교사')
            ->assertSee('2026-06-18')
            ->assertSee('교사 지원 및 참관');
    }

    public function test_latest_support_view_includes_teacher_with_legacy_new_teacher_only(): void
    {
        $admin = $this->createAdminUser();
        $year = 2024;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '레거시신규', forLatestView: false);

        \DB::table('S_Support_NewTeacher')->insert([
            'TR_Name' => 'Coach A',
            'SK_Code' => 'SK001',
            'Teacher' => '레거시신규',
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-02-17 00:00:00',
            'Status' => '완료',
            'ReportType' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->assertSee('신규교사 지원')
            ->assertSee('레거시신규')
            ->assertSee('2024-02-17')
            ->assertSee('교사 지원(신규교사)');
    }

    public function test_latest_support_view_orders_legacy_new_teacher_by_support_date(): void
    {
        $admin = $this->createAdminUser();
        $year = 2024;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $olderId = $this->createTeacher('SK001', '먼저신규', forLatestView: false);
        $newerId = $this->createTeacher('SK001', '나중신규', forLatestView: false);

        \DB::table('S_Support_NewTeacher')->insert([
            [
                'TR_Name' => 'Coach A',
                'SK_Code' => 'SK001',
                'Teacher' => '먼저신규',
                'TeacherId' => $olderId,
                'SupportDate' => '2024-01-10 00:00:00',
                'Status' => '완료',
                'ReportType' => 1,
            ],
            [
                'TR_Name' => 'Coach A',
                'SK_Code' => 'SK001',
                'Teacher' => '나중신규',
                'TeacherId' => $newerId,
                'SupportDate' => '2024-05-21 00:00:00',
                'Status' => '완료',
                'ReportType' => 1,
            ],
        ]);

        $names = collect(
            Livewire::actingAs($admin)
                ->test(CoachTeacherSupportList::class)
                ->set('filterYear', $year)
                ->viewData('teachers')
                ->items()
        )->pluck('Name')->all();

        $this->assertSame(['나중신규', '먼저신규'], $names);
    }

    public function test_coach_with_no_alias_sees_nothing(): void
    {
        $coach = $this->createCoachUser('Unknown Coach', 'unknown@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);

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
        ], forLatestView: false);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class);

        $kpis = $component->viewData('kpis');

        $this->assertSame(2, $kpis['first_round']);
        $this->assertSame(1, $kpis['second_round']);
        $this->assertSame(0, $kpis['third_round']);
        $this->assertSame(0, $kpis['fourth_round']);
        $this->assertSame(0, $kpis['completed']);
        $this->assertSame(2, $kpis['unsupported']);
        $this->assertSame(3, TeacherSupportKpiCalculator::totalSupportCount($kpis));
        $this->assertSame(1, $kpis['institution_count']);
        $this->assertSame(3, $kpis['teacher_count']);

        $component->assertSee('총 지원 횟수')
            ->assertSee('기관 수')
            ->assertSee('교사 수');
    }

    public function test_kpi_counts_third_and_fourth_round_completion(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');

        $this->createTeacher('SK001', '3차교사', [
            '_3rd_Support_Date' => "{$year}-07-10",
            'Plan_3rd_Support_Date' => "{$year}-07-01",
        ]);

        $this->createTeacher('SK001', '전차교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            '_2nd_Support_Date' => "{$year}-05-10",
            '_3rd_Support_Date' => "{$year}-07-10",
            '_4th_Support_Date' => "{$year}-09-10",
            'Plan_1st_Support_Date' => "{$year}-03-01",
            'Plan_2nd_Support_Date' => "{$year}-05-01",
            'Plan_3rd_Support_Date' => "{$year}-07-01",
            'Plan_4th_Support_Date' => "{$year}-09-01",
        ]);

        $kpis = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->viewData('kpis');

        $this->assertSame(2, $kpis['third_round']);
        $this->assertSame(1, $kpis['fourth_round']);
        $this->assertSame(1, $kpis['completed']);
    }

    public function test_kpi_filter_unsupported_matches_excel_serial_plan_dates(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '엑셀미지원', [
            'Plan_1st_Support_Date' => (string) ExcelSerialDate::dateToSerial(
                Carbon::create($year, 6, 1),
            ),
        ], forLatestView: false);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year);

        $this->assertSame(1, $component->viewData('kpis')['unsupported']);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->call('setKpiFilter', 'unsupported')
            ->assertSee('엑셀미지원');
    }

    public function test_kpi_filter_click_filters_table(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');

        $this->createTeacher('SK001', '완료교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            '_2nd_Support_Date' => "{$year}-05-15",
            '_3rd_Support_Date' => "{$year}-07-15",
            '_4th_Support_Date' => "{$year}-09-15",
            'Plan_1st_Support_Date' => "{$year}-03-01",
            'Plan_2nd_Support_Date' => "{$year}-05-01",
            'Plan_3rd_Support_Date' => "{$year}-07-01",
            'Plan_4th_Support_Date' => "{$year}-09-01",
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

    public function test_position_filter_defaults_to_teacher_and_hides_director(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '수업교사', [
            'Position' => '교사',
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK001', '원장선생님', [
            'Position' => '원장',
            '_1st_Support_Date' => "{$year}-03-11",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSet('filterPosition', 'teacher')
            ->assertSee('수업교사')
            ->assertDontSee('원장선생님')
            ->set('filterPosition', '')
            ->assertSee('수업교사')
            ->assertSee('원장선생님');
    }

    public function test_kpi_counts_follow_position_filter(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '수업교사', [
            'Position' => '교사',
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK001', '원장선생님', [
            'Position' => '원장',
            '_1st_Support_Date' => "{$year}-03-11",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year);

        $this->assertSame(1, $component->viewData('kpis')['teacher_count']);
        $this->assertSame(1, $component->viewData('kpis')['any_completed']);

        $component->set('filterPosition', '');

        $this->assertSame(2, $component->viewData('kpis')['teacher_count']);
        $this->assertSame(2, $component->viewData('kpis')['any_completed']);
    }

    public function test_kpi_filter_any_completed_hides_teachers_without_completion(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '지원한교사', [
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK001', '아직미지원', forLatestView: false);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year);

        $this->assertSame(1, $component->viewData('kpis')['any_completed']);
        $this->assertSame(2, $component->viewData('kpis')['teacher_count']);

        $component->call('setKpiFilter', 'any_completed')
            ->assertSee('지원한교사')
            ->assertDontSee('아직미지원');

        $this->assertSame(1, $component->viewData('kpis')['any_completed']);
        $this->assertSame(2, $component->viewData('kpis')['teacher_count']);

        $component->call('setKpiFilter', 'any_completed')
            ->call('setKpiFilter', 'never_supported')
            ->assertSee('아직미지원')
            ->assertDontSee('지원한교사');
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

    public function test_essentials_dates_remain_visible_when_selected_year_differs(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '이수교사', [
            'GrapeSEEDEssentials' => '2024-06-15',
            'LittleSEEDEssentials' => '2023-11-20',
            '_1st_Support_Date' => '2026-03-10',
            '_2nd_Support_Date' => '2025-08-01',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', 2026)
            ->assertSee('이수교사')
            ->assertSee('2024-06-15')
            ->assertSee('2023-11-20')
            ->assertSee('2026-03-10')
            ->assertDontSee('2025-08-01');
    }

    public function test_filter_year_keeps_full_teacher_list_and_updates_yearly_cells(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');

        $this->createTeacher('SK001', '올해계획', [
            'Plan_1st_Support_Date' => '2026-03-01',
        ]);
        $this->createTeacher('SK002', '올해완료만', [
            'Plan_1st_Support_Date' => '2025-06-01',
            '_1st_Support_Date' => '2026-01-15',
        ]);
        $this->createTeacher('SK003', '작년만', [
            'Plan_1st_Support_Date' => '2025-06-01',
            '_1st_Support_Date' => '2025-12-01',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', 2026);

        $names = $component->viewData('teachers')->getCollection()->pluck('Name')->all();

        $this->assertEqualsCanonicalizing(['올해계획', '올해완료만', '작년만'], $names);
    }

    public function test_year_filter_defaults_to_current_year(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '올해교사', [
            'Plan_1st_Support_Date' => '2026-03-01',
        ]);
        $this->createTeacher('SK002', '작년교사', [
            '_1st_Support_Date' => '2025-12-01',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSet('filterYear', (string) now()->year)
            ->assertSee('전체')
            ->assertSee('올해교사')
            ->assertSee('작년교사');
    }

    public function test_year_filter_options_use_existing_support_years_from_data(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');

        $this->createTeacher('SK001', '과거연도교사', [
            '_1st_Support_Date' => '2020-02-01',
        ]);
        $this->createTeacher('SK001', '최근연도교사', [
            '_1st_Support_Date' => '2026-03-01',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('2026년')
            ->assertSee('2020년')
            ->set('filterYear', 2020)
            ->assertSee('과거연도교사')
            ->assertSee('최근연도교사');
    }

    public function test_year_filter_stays_when_coach_and_month_change(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '올해교사', [
            '_1st_Support_Date' => "{$year}-03-01",
        ]);
        $this->createTeacher('SK002', '작년교사', [
            '_1st_Support_Date' => ($year - 1).'-05-15',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', (string) $year)
            ->set('filterCoach', 'Coach B')
            ->assertSet('filterYear', (string) $year)
            ->assertSee($year.'년')
            ->set('filterMonth', '5')
            ->assertSet('filterYear', (string) $year)
            ->assertSee($year.'년');
    }

    public function test_hidden_institution_excluded(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
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
        $this->createTeacher('SK001', '김교사', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_2nd_Support_Date' => "{$year}-06-01",
        ]);
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

    public function test_list_shows_employment_type_after_position(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK100', '고용형태기관', 'Coach A');
        $this->createTeacher('SK100', '정규교사', [
            'Position' => '교사',
            'EmploymentType' => TeacherEmploymentType::FullTime->value,
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK100', '미지정교사', [
            'Position' => '교사',
            'EmploymentType' => TeacherEmploymentType::Unspecified->value,
            '_1st_Support_Date' => "{$year}-03-11",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('근무형태')
            ->assertSee('정규교사')
            ->assertSee('Full Time')
            ->assertSee('미지정교사')
            ->assertSee('미지정');
    }

    public function test_employment_type_filter_defaults_to_all_values(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK110', '근무형태필터기관', 'Coach A');
        $this->createTeacher('SK110', '풀타임교사', [
            'EmploymentType' => TeacherEmploymentType::FullTime->value,
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK110', '파트타임교사', [
            'EmploymentType' => TeacherEmploymentType::PartTime->value,
            '_1st_Support_Date' => "{$year}-03-11",
        ]);
        $this->createTeacher('SK110', '미지정교사', [
            'EmploymentType' => TeacherEmploymentType::Unspecified->value,
            '_1st_Support_Date' => "{$year}-03-12",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSet('filterEmploymentType', '')
            ->assertSee('풀타임교사')
            ->assertSee('파트타임교사')
            ->assertSee('미지정교사');
    }

    public function test_employment_type_filter_narrows_list_and_kpi_counts(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK111', '근무형태KPI기관', 'Coach A');
        $this->createTeacher('SK111', '풀타임완료', [
            'EmploymentType' => TeacherEmploymentType::FullTime->value,
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK111', '파트타임완료', [
            'EmploymentType' => TeacherEmploymentType::PartTime->value,
            '_1st_Support_Date' => "{$year}-03-11",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', (string) $year);

        $this->assertSame(2, $component->viewData('kpis')['teacher_count']);
        $this->assertSame(2, $component->viewData('kpis')['any_completed']);

        $component->set('filterEmploymentType', TeacherEmploymentType::FullTime->value)
            ->assertSee('풀타임완료')
            ->assertDontSee('파트타임완료');

        $this->assertSame(1, $component->viewData('kpis')['teacher_count']);
        $this->assertSame(1, $component->viewData('kpis')['any_completed']);
    }

    public function test_employment_type_filter_unspecified_includes_unspecified_rows(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK112', '미지정필터기관', 'Coach A');
        $this->createTeacher('SK112', '미지정기본', [
            'EmploymentType' => TeacherEmploymentType::Unspecified->value,
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK112', '미지정빈값', [
            '_1st_Support_Date' => "{$year}-03-11",
        ]);
        $this->createTeacher('SK112', '풀타임교사', [
            'EmploymentType' => TeacherEmploymentType::FullTime->value,
            '_1st_Support_Date' => "{$year}-03-12",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', (string) $year)
            ->set('filterEmploymentType', TeacherEmploymentType::Unspecified->value)
            ->assertSee('미지정기본')
            ->assertSee('미지정빈값')
            ->assertDontSee('풀타임교사');
    }

    public function test_kpis_follow_teacher_list_visibility_filter(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '수업참여완료', [
            'ClassInOut' => true,
            'Plan_1st_Support_Date' => "{$year}-03-01",
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK001', '수업미참여완료', [
            'ClassInOut' => false,
            'Plan_1st_Support_Date' => "{$year}-03-01",
            '_1st_Support_Date' => "{$year}-03-10",
        ]);
        $this->createTeacher('SK001', '퇴직완료', [
            'Status' => '퇴직',
            'ClassInOut' => true,
            'Plan_1st_Support_Date' => "{$year}-03-01",
            '_1st_Support_Date' => "{$year}-03-10",
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

    public function test_list_sorted_by_created_date_desc(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '오래된교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
            'Created_Date' => '2025-01-01 10:00:00',
        ]);
        $this->createTeacher('SK001', '최근교사', [
            'Plan_1st_Support_Date' => "{$year}-04-01",
            'Created_Date' => '2026-06-01 10:00:00',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class);

        $teachers = $component->viewData('teachers');
        $names = collect($teachers->items())->pluck('Name')->values()->all();

        $this->assertSame('최근교사', $names[0]);
        $this->assertSame('오래된교사', $names[1]);
    }

    public function test_month_filter_matches_actual_support_only(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '3월계획만', ['Plan_1st_Support_Date' => "{$year}-03-01"], forLatestView: false);
        $this->createTeacher('SK001', '5월계획만', [
            'Plan_2nd_Support_Date' => "{$year}-05-01",
            'Plan_1st_Support_Date' => "{$year}-01-01",
        ], forLatestView: false);
        $this->createTeacher('SK001', '5월완료', [
            '_1st_Support_Date' => "{$year}-05-20",
            'Plan_1st_Support_Date' => "{$year}-01-01",
        ]);
        $this->createTeacher('SK001', '3월완료', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->set('filterMonth', '3')
            ->assertSee('3월완료')
            ->assertDontSee('3월계획만')
            ->assertDontSee('5월계획만')
            ->assertDontSee('5월완료');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->set('filterMonth', '5')
            ->assertSee('5월완료')
            ->assertDontSee('5월계획만')
            ->assertDontSee('3월계획만')
            ->assertDontSee('3월완료');
    }

    public function test_month_filter_respects_selected_filter_year(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '2026년1월', ['_1st_Support_Date' => '2026-01-15']);
        $this->createTeacher('SK001', '2025년1월', ['_1st_Support_Date' => '2025-01-15']);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', 2026)
            ->set('filterMonth', '1')
            ->assertSee('2026년1월')
            ->assertDontSee('2025년1월');
    }

    public function test_kpi_counts_reflect_month_and_coach_filters(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');

        $this->createTeacher('SK001', '3월완료', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);
        $this->createTeacher('SK002', '4월완료', [
            '_1st_Support_Date' => "{$year}-04-10",
            'Plan_1st_Support_Date' => "{$year}-04-01",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year);

        $this->assertSame(2, $component->viewData('kpis')['first_round']);

        $component
            ->set('filterMonth', '3')
            ->set('filterCoach', 'Coach A');

        $this->assertSame(1, $component->viewData('kpis')['first_round']);
    }

    public function test_kpi_filter_with_month_shows_matching_teachers(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');

        $this->createTeacher('SK001', '3월완료', [
            '_1st_Support_Date' => "{$year}-03-10",
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);
        $this->createTeacher('SK001', '4월완료', [
            '_1st_Support_Date' => "{$year}-04-10",
            'Plan_1st_Support_Date' => "{$year}-04-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->set('filterMonth', '3')
            ->call('setKpiFilter', 'first_round')
            ->assertSee('3월완료')
            ->assertDontSee('4월완료');
    }

    public function test_month_filter_uses_second_completed_date_when_round_two_selected(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '5월2차', [
            'Plan_1st_Support_Date' => "{$year}-01-01",
            'Plan_2nd_Support_Date' => "{$year}-05-01",
            '_2nd_Support_Date' => "{$year}-05-20",
        ]);
        $this->createTeacher('SK001', '3월1차', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
            '_1st_Support_Date' => "{$year}-03-10",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->set('filterRound', '2')
            ->set('filterMonth', '5')
            ->assertSee('5월2차')
            ->assertDontSee('3월1차');
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

    public function test_save_edit_form_updates_third_and_fourth_plan_schedule(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '김교사');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openEditModal', $id)
            ->set('editForm.plan_3rd', "{$year}-07-01")
            ->set('editForm.plan_type_3rd', 'On-site')
            ->set('editForm.plan_4th', "{$year}-12-01")
            ->set('editForm.plan_type_4th', 'LVA+FB')
            ->call('saveEditForm')
            ->assertHasNoErrors();

        $teacher = Teacher::find($id);
        $cols = config('coach_teacher_support.columns');

        $this->assertSame("{$year}-07-01", $teacher->{$cols['plan_3rd']}->format('Y-m-d'));
        $this->assertSame('On-site', $teacher->{$cols['plan_type_3rd']});
        $this->assertSame("{$year}-12-01", $teacher->{$cols['plan_4th']}->format('Y-m-d'));
        $this->assertSame('LVA+FB', $teacher->{$cols['plan_type_4th']});
    }

    public function test_list_displays_third_and_fourth_plan_columns(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '3차4차교사', [
            'Plan_3rd_Support_Date' => "{$year}-07-01",
            'Plan_3rd_Support_Type' => 'On-site',
            'Plan_4th_Support_Date' => "{$year}-12-01",
            'Plan_4th_Support_Type' => 'LVA+FB',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertDontSee('3차 지원 계획일자')
            ->assertDontSee('4차 지원 계획일자')
            ->set('showExtendedColumns', true)
            ->assertSee('3차 지원 계획일자')
            ->assertSee('4차 지원 계획일자')
            ->assertSee("{$year}년 7월")
            ->assertSee("{$year}년 12월")
            ->assertSee('On-site')
            ->assertSee('LVA+FB');
    }

    public function test_list_displays_third_and_fourth_completion_columns_in_default_view(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '3차4차완료교사', [
            '_3rd_Support_Date' => "{$year}-07-15",
            '_3rd_Support_Type' => 'Open-Class',
            '_4th_Support_Date' => "{$year}-12-10",
            '_4th_Support_Type' => 'LVA + FB',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('3차 완료')
            ->assertSee('4차 완료')
            ->assertSee("{$year}-07-15")
            ->assertSee('Open-Class')
            ->assertSee("{$year}-12-10")
            ->assertSee('LVA + FB');
    }

    public function test_teacher_modal_shows_create_pills_when_no_history_and_class_out(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '수업미참여', [
            'ClassInOut' => false,
            'Plan_1st_Support_Date' => '2026-03-01',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id)
            ->assertSet('showTeacherModal', true)
            ->assertSee('지원 내역 없음')
            ->assertSee('교사 지원 신규 작성:')
            ->assertSee('LVA + FR')
            ->call('openLvaFrModal', $id)
            ->assertSet('showLvaFrModal', true);
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

    public function test_save_edit_form_handles_unauthorized_teacher_gracefully(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $teacherId = $this->createTeacher('SK002', '권한없음교사');

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->set('editingTeacherId', $teacherId)
            ->set('editForm.completed_1st', "{$year}-05-01")
            ->call('saveEditForm')
            ->assertHasErrors(['editForm']);
    }

    public function test_save_edit_form_handles_information_only_terminated_teacher_gracefully(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createAccountInformationOnly('SK2693', '경기 고양 오늘유치원 (젬스톤 원마운트)', 'Coach A', '해지');
        $teacherId = $this->createTeacher('*SK2693', '테스트교사');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('editingTeacherId', $teacherId)
            ->set('editForm.completed_1st', "{$year}-06-15")
            ->call('saveEditForm')
            ->assertHasErrors(['editForm']);
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

    public function test_save_demo_lesson_report_ignores_tampered_sk_and_names(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openDemoLessonModal', $teacherId)
            ->set('demoLessonForm.sk_code', 'SK-HACKED')
            ->set('demoLessonForm.institution_name', '조작기관')
            ->set('demoLessonForm.teacher_name', '조작교사')
            ->set('demoLessonForm.support_date', '2026-05-20')
            ->set('demoLessonMarkCompleted', true)
            ->call('saveDemoLessonReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_demo_lesson_support_reports', [
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'institution_name' => '기관A',
            'teacher_name' => '홍길동',
            'status' => '완료',
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'Target' => '홍길동',
            'Support_Type' => config('coach_teacher_demo_lesson.support_type_label'),
        ]);
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
        $id = $this->createTeacher('SK001', '홍길동', forLatestView: false);

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

    public function test_teacher_list_does_not_duplicate_rows_when_account_name_tables_have_multiple_records_per_sk(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        \DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-DUPLICATE',
            'AccountName' => '레거시 기관명',
        ]);

        \DB::table('S_Account_Information')->insert([
            ['SK_Code' => 'SK-DUPLICATE', 'Account_Name' => '마스터 기관명 A', 'TR' => 'Coach A'],
            ['SK_Code' => 'SK-DUPLICATE', 'Account_Name' => '마스터 기관명 B', 'TR' => 'Coach A'],
        ]);

        $teacherId = $this->createTeacher('SK-DUPLICATE', '중복검증교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('중복검증교사');

        $teacherItems = collect($component->viewData('teachers')->items());

        $this->assertCount(1, $teacherItems);
        $this->assertSame([$teacherId], $teacherItems->pluck('ID')->all());
    }

    public function test_terminated_institution_shows_termination_badge(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK-TERM', '해지 테스트 기관', 'Coach A', '해지');
        $teacherId = $this->createTeacher('SK-TERM', '해지기관교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('해지 테스트 기관')
            ->assertSeeHtml('coach-support-inst-link--terminated')
            ->call('openInstitutionModal', 'SK-TERM')
            ->assertSet('institutionInfo.is_terminated', true)
            ->call('openTeacherModal', $teacherId)
            ->assertSet('teacherDetailInfo.is_terminated', true);
    }

    public function test_terminated_institution_link_is_red_when_teacher_sk_code_has_asterisk_prefix(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK2693', '경기 고양 젬스톤 원마운트부설', 'Coach A', '해지');
        $this->createTeacher('*SK2693', '테스트교사', [
            'School_Name' => '경기 고양 젬스톤 원마운트부설',
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('경기 고양 젬스톤 원마운트부설')
            ->assertSeeHtml('coach-support-inst-link--terminated');
    }

    public function test_terminated_institution_link_is_red_when_only_account_information_exists(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createAccountInformationOnly(
            'SK2693',
            '경기 고양 오늘유치원 (젬스톤 원마운트)',
            'Coach A',
            '해지',
        );
        $teacherId = $this->createTeacher('*SK2693', '테스트교사', [
            'School_Name' => '경기 고양 오늘유치원 (젬스톤 원마운트)',
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertSee('경기 고양 오늘유치원 (젬스톤 원마운트)')
            ->assertSeeHtml('coach-support-inst-link--terminated')
            ->call('openInstitutionModal', '*SK2693')
            ->assertSet('institutionInfo.is_terminated', true)
            ->assertSet('institutionInfo.name', '경기 고양 오늘유치원 (젬스톤 원마운트)')
            ->call('openTeacherModal', $teacherId)
            ->assertSet('teacherDetailInfo.is_terminated', true)
            ->assertSet('teacherDetailInfo.school_name', '경기 고양 오늘유치원 (젬스톤 원마운트)');
    }

    public function test_terminated_institution_cannot_open_edit_modal(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK-TERM-EDIT', '해지 일정 수정 테스트', 'Coach A', '해지');
        $teacherId = $this->createTeacher('SK-TERM-EDIT', '해지기관교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class);

        $this->assertFalse($component->instance()->canOpenEditModal($teacherId));

        $component
            ->call('openEditModal', $teacherId)
            ->assertSet('showEditModal', false);
    }

    public function test_terminated_institution_cannot_save_edit_form_even_for_admin(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createAccountInformationOnly('SK2693', '경기 고양 오늘유치원 (젬스톤 원마운트)', 'Coach A', '해지');
        $teacherId = $this->createTeacher('*SK2693', '테스트교사', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
        ]);

        $action = new UpdateTeacherSupport;

        $this->expectException(AuthorizationException::class);

        $action->execute($teacherId, ['completed_1st' => "{$year}-06-15"], $admin);
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

    public function test_coach_can_open_institution_modal_for_other_coach_scope_read_only(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $this->createTeacher('SK001', '담당교사');
        $this->createTeacher('SK002', '비담당교사');

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK002')
            ->assertSet('showInstitutionModal', true)
            ->assertSet('institutionInfo.sk_code', 'SK002');
    }

    public function test_coach_can_open_institution_modal_for_in_scope_sk_code(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '담당교사');

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK001')
            ->assertSet('showInstitutionModal', true)
            ->assertSet('institutionInfo.sk_code', 'SK001')
            ->assertSet('institutionInfo.name', '기관A');
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

    public function test_institution_support_history_row_opens_detail_modal(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');

        \Illuminate\Support\Facades\DB::table('S_SupportInfo_Account')->insert([
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'TR_Name' => 'James Kwak',
            'Support_Date' => '2026-02-25 00:00:00',
            'Support_Type' => '대면',
            'Issue' => '설명회',
            'Status' => '완료',
            'CompletedDate' => now(),
        ]);

        $supportId = (int) \Illuminate\Support\Facades\DB::table('S_SupportInfo_Account')->max('ID');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK001');

        $history = $component->get('institutionSupportHistory');
        $this->assertCount(1, $history);
        $this->assertSame('account:'.$supportId, $history[0]['detail_key']);

        $component
            ->call('openTeacherSupportHistoryDetail', 'account:'.$supportId, null)
            ->assertSet('showTeacherSupportHistoryDetailModal', true)
            ->assertSet('selectedTeacherSupportHistoryDetail.title', '대면');

        $fields = collect($component->get('selectedTeacherSupportHistoryDetail')['sections'][0]['fields']);
        $this->assertSame('설명회', $fields->firstWhere('label', '기관이슈')['value']);
        $this->assertSame('James Kwak', $fields->firstWhere('label', '담당자(Coach)')['value']);
    }

    public function test_institution_modal_teacher_support_history_row_opens_detail_modal(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '류영이 원장');

        \Illuminate\Support\Facades\DB::table('S_Support_OnSite')->insert([
            'TR_Name' => 'James Kwak',
            'SK_Code' => 'SK001',
            'Teacher' => '류영이 원장',
            'TeacherId' => $teacherId,
            'SupportDate' => '2026-02-25 00:00:00',
            'Status' => '완료',
        ]);

        $supportId = (int) \Illuminate\Support\Facades\DB::table('S_Support_OnSite')->max('ID');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK001')
            ->call('openTeacherSupportHistoryDetail', 'legacy:S_Support_OnSite:'.$supportId, $teacherId)
            ->assertSet('supportReportViewMode', true)
            ->assertSet('showOnsiteModal', true)
            ->assertSet('onsiteForm.coach_name', 'James Kwak');
    }

    public function test_institution_modal_teacher_support_history_excludes_account_records(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '류영이 원장');

        \Illuminate\Support\Facades\DB::table('S_SupportInfo_Account')->insert([
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'TR_Name' => 'James Kwak',
            'Target' => '류영이 원장',
            'Support_Date' => '2026-02-25 00:00:00',
            'Support_Type' => '대면',
            'Issue' => '설명회',
            'Status' => '완료',
            'CompletedDate' => now(),
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK001');

        $this->assertCount(1, $component->get('institutionSupportHistory'));
        $this->assertSame([], $component->get('teacherSupportHistory'));
    }

    public function test_institution_modal_teacher_support_history_includes_legacy_onsite(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '류영이 원장');

        \Illuminate\Support\Facades\DB::table('S_Support_OnSite')->insert([
            'TR_Name' => 'Levi Kim',
            'SK_Code' => 'SK001',
            'Teacher' => '류영이 원장',
            'TeacherId' => $teacherId,
            'SupportDate' => '2024-04-15 00:00:00',
            'Status' => '완료',
        ]);

        $history = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK001')
            ->get('teacherSupportHistory');

        $this->assertCount(1, $history);
        $this->assertSame('Levi Kim', $history[0]['coach']);
        $this->assertSame('류영이 원장', $history[0]['teacher']);
        $this->assertSame('교사 지원 On-Site', $history[0]['type']);
        $this->assertStringStartsWith('legacy:S_Support_OnSite:', (string) $history[0]['detail_key']);
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

    public function test_schedule_cells_render_without_legacy_edit_button_column(): void
    {
        $admin = $this->createAdminUser();
        $year = now()->year;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createTeacher('SK001', '홍길동', [
            'Plan_1st_Support_Date' => "{$year}-03-01",
            'Plan_1st_Support_Type' => 'On-site',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class);

        $html = $component->html();

        $this->assertStringContainsString('coach-teacher-support-table-scroll', $html);
        $this->assertStringContainsString('coach-support-schedule-cell', $html);
        $this->assertStringContainsString('coach-support-mobile-card', $html);
        // 지원 일정 수정 모달 임시 비활성화 상태: 셀 클릭 트리거가 렌더링되지 않아야 한다.
        // 모달 복구 시 assertStringContainsString 으로 되돌릴 것.
        $this->assertStringNotContainsString('wire:click="openEditModal(', $html);
        $this->assertStringContainsString('wire:click.stop="openTeacherModal(', $html);
        $this->assertStringNotContainsString('>일정 수정<', $html);
    }

    public function test_open_edit_modal_prefills_form_matching_table_display(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '신혜정', [
            'Plan_1st_Support_Date' => '45778',
            'Plan_1st_Support_Type' => 'LVA+FB',
            'Plan_2nd_Support_Date' => '45931',
            'Plan_2nd_Support_Type' => 'LVA+FB',
            '_1st_Support_Date' => '45840',
            '_1st_Support_Type' => 'LVA+FB',
            '_2nd_Support_Date' => '45809',
            '_2nd_Support_Type' => 'On-Site',
            '_3rd_Support_Date' => '45931',
            '_3rd_Support_Type' => '방문',
            '_4th_Support_Date' => null,
            '_4th_Support_Type' => null,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', 2025)
            ->call('openEditModal', $id)
            ->assertSet('showEditModal', true);

        $this->assertSame('2025-05-01', $component->get('editForm.plan_1st'));
        $this->assertSame('LVA+FB', $component->get('editForm.plan_type_1st'));
        $this->assertSame('2025-10-01', $component->get('editForm.plan_2nd'));
        $this->assertSame('LVA+FB', $component->get('editForm.plan_type_2nd'));
        $this->assertSame('2025-07-02', $component->get('editForm.completed_1st'));
        $this->assertSame('LVA+FB', $component->get('editForm.type_1st'));
        $this->assertSame('2025-06-01', $component->get('editForm.completed_2nd'));
        $this->assertSame('On-Site', $component->get('editForm.type_2nd'));
        $this->assertSame('2025-10-01', $component->get('editForm.completed_3rd'));
        $this->assertSame('방문', $component->get('editForm.type_3rd'));
        $this->assertSame('', $component->get('editForm.completed_4th'));
        $this->assertSame('', $component->get('editForm.type_4th'));

        $html = $component->html();
        $this->assertStringContainsString('2025년 5월', $html);
        $this->assertStringContainsString('2025년 10월', $html);
        $this->assertStringContainsString('2025-07-02', $html);
        $this->assertStringContainsString('2025-06-01', $html);
        $this->assertStringContainsString('LVA+FB', $html);
        $this->assertStringContainsString('value="LVA+FB"', $html);
        $this->assertStringContainsString('value="On-Site"', $html);
    }

    public function test_institution_click_does_not_open_edit_modal(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        $component = Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openInstitutionModal', 'SK001');

        $this->assertTrue($component->get('showInstitutionModal'));
        $this->assertFalse($component->get('showEditModal'));

        $component
            ->call('openEditModal', $id)
            ->assertSet('showEditModal', true);
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
            ->assertSee('교사정보 수정하기')
            ->assertSee('저장하기')
            ->assertSee('취소')
            ->assertDontSee('>수정하기<', false)
            ->set('teacherProfileForm.name', '김수정')
            ->set('teacherProfileForm.email', 'new@test.com')
            ->set('teacherProfileForm.employment_type', 'full_time')
            ->set('teacherProfileForm.class_participation', 'out')
            ->call('saveTeacherProfile')
            ->assertHasNoErrors();

        $teacher = Teacher::find($id);
        $this->assertSame('김수정', $teacher->Name);
        $this->assertSame('new@test.com', $teacher->Email);
        $this->assertSame('full_time', $teacher->EmploymentType?->value ?? $teacher->getAttributes()['EmploymentType']);
        $this->assertFalse((bool) $teacher->getAttributes()['ClassInOut']);
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
            ->set('retireRecommendChoice', 'no')
            ->call('retireTeacher');

        $teacher = Teacher::find($id);
        $this->assertFalse($teacher->ClassInOut);
        $this->assertSame('퇴직', $teacher->Status);

        $this->assertDatabaseHas('S_RetirementList', [
            'TearcherID' => $id,
            'Status' => '퇴직',
            'RecommendYN' => 0,
            'RecommendDescription' => '해당사항없음',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->assertDontSee('퇴직대상');
    }

    public function test_class_out_teacher_with_unset_status_can_retire_from_modal(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '한민희 교수부장', [
            'ClassInOut' => false,
            'Status' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id)
            ->assertSet('teacherDetailInfo.is_retired', false)
            ->assertSeeHtml('wire:click="confirmRetireTeacher"')
            ->call('confirmRetireTeacher')
            ->set('retireRecommendChoice', 'no')
            ->call('retireTeacher');

        $teacher = Teacher::find($id);
        $this->assertSame('퇴직', $teacher->Status);
        $this->assertFalse($teacher->ClassInOut);
    }

    public function test_retired_teacher_modal_hides_retire_and_edit_actions(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '이미퇴직', [
            'Status' => '퇴직',
            'ClassInOut' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('showAllTeachers', true)
            ->call('openTeacherModal', $id)
            ->assertSet('teacherDetailInfo.is_retired', true)
            ->assertDontSeeHtml('wire:click="confirmRetireTeacher"')
            ->assertDontSeeHtml('wire:click="startTeacherEdit"');
    }

    public function test_coach_can_view_but_cannot_edit_teacher_outside_scope(): void
    {
        $coach = $this->createCoachUser('Coach A', 'coacha@example.com');

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $this->createInstitution('SK002', '기관B', 'Coach B');
        $id = $this->createTeacher('SK002', '이교사');

        $component = Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id);

        $this->assertTrue($component->get('showTeacherModal'));

        $component->call('startTeacherEdit');
        $this->assertFalse($component->get('teacherModalEditMode'));
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

    public function test_mount_opens_typed_create_modal_from_query_params(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->withQueryParams([
                'teacher_id' => $id,
                'create_action' => 'pro_con',
                'sk_code' => 'SK001',
            ])
            ->test(CoachTeacherSupportList::class)
            ->assertSet('showProConModal', true)
            ->assertSet('proConForm.teacher_name', '홍길동');
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

    public function test_save_lva_fr_report_does_not_double_append_seconds_to_meet_time(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLvaFrModal', $id)
            ->set('lvaFrForm.support_date', '2026-05-19')
            ->set('lvaFrForm.interview_date', '2026-05-19')
            ->set('lvaFrForm.interview_time', '14:30:00')
            ->set('lvaFrMarkCompleted', true)
            ->call('saveLvaFrReport')
            ->assertHasNoErrors();

        $meetTime = (string) \Illuminate\Support\Facades\DB::table('S_SupportInfo_Account')
            ->where('SK_Code', 'SK001')
            ->where('Target', '홍길동')
            ->value('Meet_Time');

        $this->assertNotSame('14:30:00:00', $meetTime);
        $this->assertStringContainsString('14:30', $meetTime);
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
        $id = $this->createTeacher('SK001', '홍길동', forLatestView: false);

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

        $this->assertDatabaseHas('Teachers', [
            'ID' => $id,
            '_1st_Support_Type' => 'LVA + FB',
        ]);
    }

    public function test_save_lva_fb_report_rejects_recorded_support_round(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동', [
            '_1st_Support_Date' => '2026-01-10',
            '_1st_Support_Type' => '방문',
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openLvaFbModal', $id)
            ->set('supportRound', '1')
            ->set('lvaFbMarkCompleted', true)
            ->call('saveLvaFbReport')
            ->assertHasErrors(['support_round']);
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

    public function test_mochi_support_history_allows_edit_for_author(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openOnsiteModal', $teacherId)
            ->set('onsiteForm.support_date', '2026-05-19')
            ->set('onsiteForm.other_notes', '초기 메모')
            ->set('onsiteMarkCompleted', true)
            ->call('saveOnsiteReport')
            ->assertHasNoErrors();

        $reportId = (int) \DB::table('teacher_onsite_support_reports')->max('id');
        $detailKey = 'mochi:teacher_onsite_support_reports:'.$reportId;

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $teacherId)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->assertSet('supportReportViewMode', true)
            ->assertSet('showOnsiteModal', true)
            ->assertSet('viewingSupportReportDetailKey', $detailKey)
            ->call('startSupportReportEdit')
            ->assertSet('supportReportViewMode', false);
    }

    public function test_edit_keeps_saved_support_round_instead_of_recommending_next(): void
    {
        $admin = $this->createAdminUser();
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동', [
            '_1st_Support_Date' => '2026-01-10',
            '_1st_Support_Type' => 'On-Site',
            '_2nd_Support_Date' => '2026-02-10',
            '_2nd_Support_Type' => 'LVA + FR',
        ], forLatestView: false);

        \DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '홍길동',
            'support_date' => '2026-07-15',
            'support_purpose' => '정기수업참관',
            'session_number' => null,
            'meeting_type' => 'On-Site',
            'monitoring_feedback' => '모니터링',
            'status' => '완료',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = (int) \DB::table('teacher_visit_support_reports')->max('id');
        $detailKey = 'mochi:teacher_visit_support_reports:'.$reportId;

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $teacherId)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->assertSet('supportReportViewMode', true)
            ->assertSet('showVisitModal', true)
            ->assertSet('supportRound', '')
            ->call('startSupportReportEdit')
            ->assertSet('supportReportViewMode', false)
            ->assertSet('supportRound', '');
    }

    public function test_edit_preserves_explicit_saved_session_number(): void
    {
        $admin = $this->createAdminUser();
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동', forLatestView: false);

        \DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '홍길동',
            'support_date' => '2026-07-15',
            'support_purpose' => '정기수업참관',
            'session_number' => 2,
            'meeting_type' => 'On-Site',
            'monitoring_feedback' => '모니터링',
            'status' => '완료',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = (int) \DB::table('teacher_visit_support_reports')->max('id');
        $detailKey = 'mochi:teacher_visit_support_reports:'.$reportId;

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $teacherId)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->assertSet('supportRound', '2')
            ->call('startSupportReportEdit')
            ->assertSet('supportRound', '2');
    }

    public function test_author_can_update_mochi_onsite_report_via_history(): void
    {
        $coach = $this->createCoachUser('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동');

        \DB::table('teacher_onsite_support_reports')->insert([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '홍길동',
            'support_date' => '2026-05-19',
            'other_notes' => '초기 메모',
            'procedures' => json_encode([]),
            'strength_areas' => json_encode([]),
            'growth_areas' => json_encode([]),
            'status' => '임시',
            'created_by' => $coach->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = (int) \DB::table('teacher_onsite_support_reports')->max('id');
        $detailKey = 'mochi:teacher_onsite_support_reports:'.$reportId;

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $teacherId)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->call('startSupportReportEdit')
            ->set('onsiteForm.other_notes', '수정된 메모')
            ->set('onsiteMarkCompleted', false)
            ->call('saveOnsiteReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_onsite_support_reports', [
            'id' => $reportId,
            'other_notes' => '수정된 메모',
            'status' => '임시',
        ]);
    }

    public function test_revert_completed_mochi_report_to_draft_keeps_support_record_in_progress(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openOnsiteModal', $teacherId)
            ->set('onsiteForm.support_date', '2026-05-19')
            ->set('onsiteForm.other_notes', '완료 메모')
            ->set('onsiteMarkCompleted', true)
            ->call('saveOnsiteReport')
            ->assertHasNoErrors();

        $reportId = (int) \DB::table('teacher_onsite_support_reports')->max('id');
        $supportRecordId = (int) \DB::table('teacher_onsite_support_reports')->where('id', $reportId)->value('support_record_id');
        $detailKey = 'mochi:teacher_onsite_support_reports:'.$reportId;

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $teacherId)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->call('startSupportReportEdit')
            ->set('onsiteMarkCompleted', false)
            ->call('saveOnsiteReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_onsite_support_reports', [
            'id' => $reportId,
            'status' => '임시',
            'support_record_id' => $supportRecordId,
        ]);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'ID' => $supportRecordId,
            'Status' => '진행중',
        ]);

        $this->assertNull(
            \DB::table('S_SupportInfo_Account')->where('ID', $supportRecordId)->value('CompletedDate')
        );
    }

    public function test_legacy_support_history_allows_edit_for_author(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동');

        \DB::table('S_Support_OnSite')->insert([
            'TR_Name' => 'Coach A',
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'Teacher' => '홍길동',
            'TeacherId' => $teacherId,
            'SupportDate' => '2026-05-19 00:00:00',
            'Status' => '완료',
            'Other' => '초기 메모',
        ]);

        $legacyId = (int) \DB::table('S_Support_OnSite')->max('ID');
        $detailKey = 'legacy:S_Support_OnSite:'.$legacyId;

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $teacherId)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->assertSet('supportReportViewMode', true)
            ->assertSet('showOnsiteModal', true)
            ->assertSet('viewingSupportReportDetailKey', $detailKey)
            ->call('startSupportReportEdit')
            ->assertSet('supportReportViewMode', false);
    }

    public function test_author_can_update_legacy_onsite_report_via_history(): void
    {
        $coach = $this->createCoachUser('Coach A');
        $this->createInstitution('SK001', '기관A', 'Coach A');
        $teacherId = $this->createTeacher('SK001', '홍길동');

        \DB::table('S_Support_OnSite')->insert([
            'TR_Name' => 'Coach A',
            'SK_Code' => 'SK001',
            'Account_Name' => '기관A',
            'Teacher' => '홍길동',
            'TeacherId' => $teacherId,
            'SupportDate' => '2026-05-19 00:00:00',
            'Status' => '임시',
            'Other' => '초기 메모',
        ]);

        $legacyId = (int) \DB::table('S_Support_OnSite')->max('ID');
        $detailKey = 'legacy:S_Support_OnSite:'.$legacyId;

        Livewire::actingAs($coach)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $teacherId)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $teacherId)
            ->call('startSupportReportEdit')
            ->set('onsiteForm.other_notes', '수정된 레거시 메모')
            ->set('onsiteMarkCompleted', false)
            ->call('saveOnsiteReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_Support_OnSite', [
            'ID' => $legacyId,
            'Other' => '수정된 레거시 메모',
            'Status' => '임시',
        ]);
    }

    public function test_save_visit_report_shows_alert_when_required_fields_missing(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openVisitModal', $id)
            ->set('visitForm.support_purpose', '정기 참관')
            ->set('visitMarkCompleted', true)
            ->call('saveVisitReport')
            ->assertHasErrors([
                'visitForm.monitoring_feedback',
            ])
            ->assertDispatched('visit-support-show-alert');

        $this->assertDatabaseCount('teacher_visit_support_reports', 0);
    }

    public function test_save_visit_report_updates_teacher_completion_slot(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동', forLatestView: false);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openVisitModal', $id)
            ->set('visitForm.support_purpose', '정기 참관')
            ->set('visitForm.monitoring_feedback', '모니터링 내용')
            ->set('visitForm.interview_and_action_plan', '후속 조치')
            ->set('visitMarkCompleted', true)
            ->call('saveVisitReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $id,
            '_1st_Support_Type' => '교사 지원 및 참관',
        ]);

        $this->assertNotNull(
            Teacher::query()->find($id)?->getRawOriginal('_1st_Support_Date'),
        );
    }

    public function test_completing_in_progress_visit_report_updates_teacher_completion_slot(): void
    {
        $admin = $this->createAdminUser();

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동', forLatestView: false);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openVisitModal', $id)
            ->set('visitForm.support_purpose', '정기 참관')
            ->set('visitMarkCompleted', false)
            ->call('saveVisitReport')
            ->assertHasNoErrors();

        $reportId = (int) \DB::table('teacher_visit_support_reports')->max('id');
        $detailKey = 'mochi:teacher_visit_support_reports:'.$reportId;

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->call('openTeacherModal', $id)
            ->call('openTeacherSupportHistoryDetail', $detailKey, $id)
            ->call('startSupportReportEdit')
            ->set('visitForm.monitoring_feedback', '모니터링 내용')
            ->set('visitForm.interview_and_action_plan', '후속 조치')
            ->set('visitMarkCompleted', true)
            ->call('saveVisitReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('Teachers', [
            'ID' => $id,
            '_1st_Support_Type' => '교사 지원 및 참관',
        ]);
    }

    public function test_main_list_shows_completed_visit_report_when_teacher_slot_is_empty(): void
    {
        $admin = $this->createAdminUser();
        $year = 2026;

        $this->createInstitution('SK001', '기관A', 'Coach A');
        $id = $this->createTeacher('SK001', '홍길동');

        \DB::table('teacher_visit_support_reports')->insert([
            'teacher_id' => $id,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '기관A',
            'teacher_name' => '홍길동',
            'support_date' => '2026-03-15',
            'support_purpose' => '정기 참관',
            'monitoring_feedback' => '모니터링 내용',
            'interview_and_action_plan' => '후속 조치',
            'status' => '완료',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('filterYear', $year)
            ->assertSee('홍길동')
            ->assertSee('2026-03-15')
            ->assertSee('교사 지원 및 참관');
    }
}
