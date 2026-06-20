<?php

namespace Tests\Feature;

use App\Actions\DeleteSupportRecord;
use App\Livewire\SupportList;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TeacherSupportSlotSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteSupportRecordCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    protected function createRequiredTables(): void
    {
        Schema::dropIfExists('teacher_visit_support_reports');
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');
        Schema::dropIfExists('Teachers');

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
            $table->string('Customer_Type', 255)->nullable();
        });

        Schema::create('Teachers', function ($table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 255)->nullable();
            $table->date('_1st_Support_Date')->nullable();
            $table->string('_1st_Support_Type', 100)->nullable();
        });

        Schema::create('S_SupportInfo_Account', function ($table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->dateTime('Support_Date')->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Status', 50)->nullable();
        });

        Schema::create('teacher_visit_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('support_purpose', 100);
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{support_record_id: int, report_id: int, teacher_id: int}
     */
    private function seedLinkedCompletedVisitReport(string $skCode = 'SK001'): array
    {
        $teacherId = (int) \DB::table('Teachers')->insertGetId([
            'SK_Code' => $skCode,
            'Name' => '박정현',
        ]);

        $supportRecordId = (int) \DB::table('S_SupportInfo_Account')->insertGetId([
            'Year' => 2026,
            'SK_Code' => $skCode,
            'Account_Name' => '거창 세종유치원',
            'TR_Name' => 'Levi Kim',
            'Support_Date' => '2026-06-18',
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '완료',
        ]);

        $reportId = (int) \DB::table('teacher_visit_support_reports')->insertGetId([
            'teacher_id' => $teacherId,
            'sk_code' => $skCode,
            'coach_name' => 'Levi Kim',
            'institution_name' => '거창 세종유치원',
            'teacher_name' => '박정현',
            'support_date' => '2026-06-18',
            'support_purpose' => '신임',
            'status' => '완료',
            'support_record_id' => $supportRecordId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacher = Teacher::query()->findOrFail($teacherId);
        TeacherSupportSlotSync::apply($teacher, 1, '교사 지원 및 참관', '2026-06-18');

        return [
            'support_record_id' => $supportRecordId,
            'report_id' => $reportId,
            'teacher_id' => $teacherId,
        ];
    }

    public function test_deleting_support_record_removes_linked_visit_report_and_teacher_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $seed = $this->seedLinkedCompletedVisitReport();

        $this->actingAs($admin);

        app(DeleteSupportRecord::class)(
            SupportRecord::query()->findOrFail($seed['support_record_id']),
            'SK001',
        );

        $this->assertDatabaseMissing('S_SupportInfo_Account', ['ID' => $seed['support_record_id']]);
        $this->assertDatabaseMissing('teacher_visit_support_reports', ['id' => $seed['report_id']]);

        $teacher = Teacher::query()->findOrFail($seed['teacher_id']);
        $this->assertNull($teacher->_1st_Support_Type);
        $this->assertNull($teacher->getRawOriginal('_1st_Support_Date'));
    }

    public function test_support_list_delete_record_cascades_to_teacher_support(): void
    {
        $admin = User::factory()->admin()->create();
        $seed = $this->seedLinkedCompletedVisitReport('SK-DEL');

        \DB::table('S_AccountName')->insert([
            'SKcode' => 'SK-DEL',
            'AccountName' => '삭제 기관',
        ]);

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('deleteRecord', $seed['support_record_id'])
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('S_SupportInfo_Account', ['ID' => $seed['support_record_id']]);
        $this->assertDatabaseMissing('teacher_visit_support_reports', ['id' => $seed['report_id']]);

        $teacher = Teacher::query()->findOrFail($seed['teacher_id']);
        $this->assertNull($teacher->_1st_Support_Type);
    }

    public function test_in_progress_linked_report_is_deleted_without_clearing_unrelated_teacher_slot(): void
    {
        $teacherId = (int) \DB::table('Teachers')->insertGetId([
            'SK_Code' => 'SK002',
            'Name' => '김교사',
            '_1st_Support_Date' => '2026-01-01',
            '_1st_Support_Type' => 'On-Site',
        ]);

        $supportRecordId = (int) \DB::table('S_SupportInfo_Account')->insertGetId([
            'Year' => 2026,
            'SK_Code' => 'SK002',
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '진행중',
        ]);

        $reportId = (int) \DB::table('teacher_visit_support_reports')->insertGetId([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK002',
            'coach_name' => 'Coach',
            'institution_name' => '기관',
            'teacher_name' => '김교사',
            'support_date' => '2026-06-18',
            'support_purpose' => '신임',
            'status' => '임시',
            'support_record_id' => $supportRecordId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->admin()->create());

        app(DeleteSupportRecord::class)(
            SupportRecord::query()->findOrFail($supportRecordId),
            'SK002',
        );

        $this->assertDatabaseMissing('teacher_visit_support_reports', ['id' => $reportId]);

        $teacher = Teacher::query()->findOrFail($teacherId);
        $this->assertSame('On-Site', $teacher->_1st_Support_Type);
    }
}
