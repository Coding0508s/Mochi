<?php

namespace Tests\Feature;

use App\Livewire\SupportList;
use App\Mail\SupportReportStoredMail;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SupportListCompletionMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTables();
    }

    private function createSupportTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
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

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Target', 255)->nullable();
            $table->text('TO_Account')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
            $table->timestamp('CreatedDate')->nullable();
        });
    }

    public function test_toggle_complete_sends_teacher_support_mail_for_visit_record(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['list-complete@test.org'],
        ]);

        $record = $this->createSupportRecord([
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '진행중',
            'TO_Account' => "세부 지원 내용\n모니터링 결과",
        ]);

        $admin = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('toggleComplete', (int) $record->ID)
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertTrue($record->isCompleted());

        Mail::assertSent(SupportReportStoredMail::class, function (SupportReportStoredMail $mail): bool {
            return $mail->hasTo('list-complete@test.org')
                && $mail->reportMode === 'teacher'
                && $mail->reportSavedOpening === 'Coach Team 교사 지원 보고서';
        });
    }

    public function test_toggle_complete_off_does_not_send_mail(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['list-complete@test.org'],
        ]);

        $record = $this->createSupportRecord([
            'Support_Type' => '교사 지원 및 참관',
            'Status' => '완료',
            'CompletedDate' => now(),
        ]);

        $admin = User::factory()->admin()->create(['team' => 'COACH']);

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('toggleComplete', (int) $record->ID);

        Mail::assertNothingSent();
    }

    public function test_toggle_complete_does_not_send_mail_for_institution_support_type(): void
    {
        Mail::fake();

        config([
            'support_report_mail.notify_addresses' => ['list-complete@test.org'],
        ]);

        $record = $this->createSupportRecord([
            'Support_Type' => '전화',
            'Status' => '진행중',
        ]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('toggleComplete', (int) $record->ID);

        Mail::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSupportRecord(array $overrides = []): SupportRecord
    {
        $skCode = (string) ($overrides['SK_Code'] ?? 'SK-LIST-MAIL');
        $accountName = (string) ($overrides['Account_Name'] ?? '목록 완료 메일 기관');

        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => $accountName,
        ]);

        return SupportRecord::query()->create(array_merge([
            'Year' => 2026,
            'SK_Code' => $skCode,
            'Account_Name' => $accountName,
            'TR_Name' => 'Coach A',
            'Support_Date' => '2026-06-18',
            'Meet_Time' => '11:30:00',
            'Support_Type' => '교사 지원 및 참관',
            'Target' => '김교사',
            'TO_Account' => '지원 내용',
            'Status' => '진행중',
            'CreatedDate' => now(),
        ], $overrides));
    }
}
