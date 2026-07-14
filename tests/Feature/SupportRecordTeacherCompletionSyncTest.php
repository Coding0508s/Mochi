<?php

namespace Tests\Feature;

use App\Livewire\SupportList;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TeacherSupportCompletionDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SupportRecordTeacherCompletionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
        TeacherSupportCompletionDisplay::flushRequestCache();
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
            $table->date('_2nd_Support_Date')->nullable();
            $table->string('_2nd_Support_Type', 100)->nullable();
            $table->date('_3rd_Support_Date')->nullable();
            $table->string('_3rd_Support_Type', 100)->nullable();
            $table->date('_4th_Support_Date')->nullable();
            $table->string('_4th_Support_Type', 100)->nullable();
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
            $table->timestamp('CompletedDate')->nullable();
        });

        Schema::create('teacher_visit_support_reports', function ($table): void {
            $table->id();
            $table->unsignedInteger('teacher_id');
            $table->string('sk_code', 100);
            $table->string('coach_name', 255);
            $table->string('institution_name', 255);
            $table->string('teacher_name', 255);
            $table->date('support_date');
            $table->string('support_purpose', 100)->nullable();
            $table->string('status', 20)->default('임시');
            $table->unsignedBigInteger('support_record_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{support_record_id: int, report_id: int, teacher_id: int}
     */
    private function seedDraftVisitLinkedToAccount(): array
    {
        $teacherId = (int) Teacher::query()->create([
            'SK_Code' => 'SK001',
            'Name' => '테스트교사',
        ])->ID;

        $supportRecordId = (int) SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK001',
            'Account_Name' => '테스트기관',
            'TR_Name' => 'Coach A',
            'Support_Date' => '2026-05-20',
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '진행중',
        ])->ID;

        $reportId = (int) DB::table('teacher_visit_support_reports')->insertGetId([
            'teacher_id' => $teacherId,
            'sk_code' => 'SK001',
            'coach_name' => 'Coach A',
            'institution_name' => '테스트기관',
            'teacher_name' => '테스트교사',
            'support_date' => '2026-05-20',
            'support_purpose' => '정기지원',
            'status' => '임시',
            'support_record_id' => $supportRecordId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'support_record_id' => $supportRecordId,
            'report_id' => $reportId,
            'teacher_id' => $teacherId,
        ];
    }

    public function test_toggle_complete_marks_linked_teacher_report_and_slot(): void
    {
        $seed = $this->seedDraftVisitLinkedToAccount();
        $admin = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('toggleComplete', $seed['support_record_id'])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_visit_support_reports', [
            'id' => $seed['report_id'],
            'status' => '완료',
        ]);

        $teacher = Teacher::query()->findOrFail($seed['teacher_id']);
        $this->assertSame('2026-05-20', optional($teacher->_1st_Support_Date)?->format('Y-m-d')
            ?? (string) $teacher->getRawOriginal('_1st_Support_Date'));
        $this->assertSame('교사 지원 및 참관', $teacher->_1st_Support_Type);

        TeacherSupportCompletionDisplay::flushRequestCache();
        $parts = TeacherSupportCompletionDisplay::parts($teacher->fresh(), 1, 2026);
        $this->assertSame('2026-05-20', $parts['date']);
        $this->assertSame('교사 지원 및 참관', $parts['type']);
    }

    public function test_toggle_complete_off_reverts_linked_teacher_report_and_slot(): void
    {
        $seed = $this->seedDraftVisitLinkedToAccount();
        $admin = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('toggleComplete', $seed['support_record_id'])
            ->call('toggleComplete', $seed['support_record_id'])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_visit_support_reports', [
            'id' => $seed['report_id'],
            'status' => '임시',
        ]);

        $teacher = Teacher::query()->findOrFail($seed['teacher_id']);
        $this->assertNull($teacher->getRawOriginal('_1st_Support_Date'));
        $this->assertNull($teacher->_1st_Support_Type);
    }

    public function test_toggle_complete_without_linked_report_does_not_error(): void
    {
        $supportRecordId = (int) SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK002',
            'Account_Name' => '일반기관',
            'TR_Name' => 'Coach A',
            'Support_Date' => '2026-05-21',
            'Support_Type' => '전화',
            'Status' => '진행중',
        ])->ID;

        $admin = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('toggleComplete', $supportRecordId)
            ->assertHasNoErrors();

        $this->assertTrue(SupportRecord::query()->findOrFail($supportRecordId)->isCompleted());
    }
}
